<?php

namespace App\Services\Game;

use App\Events\HandCompleted;
use App\Events\HandStarted;
use App\Events\PlayerActed;
use App\Events\TurnChanged;
use App\Jobs\AutoFoldTurn;
use App\Models\Bet;
use App\Models\Hand;
use App\Models\HandPlayer;
use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Cards\Card;
use App\Services\Cards\Deck;
use App\Services\Cards\HandComparator;
use App\Services\Cards\HandEvaluator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GameService
{
    public function __construct(
        protected HandEvaluator $evaluator,
        protected HandComparator $comparator,
        protected BettingRules $rules,
    ) {}

    /**
     * Begin a new hand at the given table.
     */
    public function startHand(PokerTable $table): Hand
    {
        return DB::transaction(function () use ($table) {
            $table->refresh();
            $seated = $table->tablePlayers()
                ->where('is_active', true)
                ->orderBy('seat_position')
                ->get();

            if ($seated->count() < 2) {
                throw new RuntimeException('Need at least 2 active players to start a hand.');
            }

            $seatPositions = $seated->pluck('seat_position')->all();

            // Rotate dealer to next active seat; remember the old one so the
            // client can animate the dealer button sliding across.
            $previousDealerSeat = $table->dealer_seat;
            $dealerSeat = $this->nextSeatFrom($table->dealer_seat, $seatPositions);
            $firstActor = $this->nextSeatFrom($dealerSeat, $seatPositions);

            // Deal/turn order, starting at the first actor (left of the dealer),
            // following the engine's seat rotation — used to choreograph the deal.
            $dealOrder = array_map(
                fn (int $seat) => [
                    'seat_position' => $seat,
                    'user_id' => $seated->firstWhere('seat_position', $seat)->user_id,
                ],
                $this->seatOrderFrom($firstActor, $seatPositions),
            );

            $deck = new Deck;
            $deck->shuffle();

            $hand = Hand::create([
                'poker_table_id' => $table->id,
                'dealer_seat' => $dealerSeat,
                'previous_dealer_seat' => $previousDealerSeat,
                'current_turn_seat' => $firstActor,
                'turn_started_at' => now(),
                'turn_deadline' => now()->addSeconds($this->turnSeconds()),
                'deal_order' => $dealOrder,
                'pot_amount' => 0,
                'current_stake' => $table->boot_amount,
                'status' => 'dealing',
                'started_at' => now(),
            ]);

            foreach ($seated as $tp) {
                /** @var TablePlayer $tp */
                $dealt = $deck->deal(3);
                $cards = array_map(fn (Card $c) => $c->toArray(), $dealt);

                $hp = HandPlayer::create([
                    'hand_id' => $hand->id,
                    'user_id' => $tp->user_id,
                    'seat_position' => $tp->seat_position,
                    'cards' => $cards,
                    'is_blind' => true,
                    'has_packed' => false,
                    'total_contributed' => 0,
                ]);

                $this->collectBoot($hand, $hp, (float) $table->boot_amount);
            }

            $hand->update(['status' => 'betting']);
            $table->update([
                'current_hand_id' => $hand->id,
                'dealer_seat' => $dealerSeat,
                'status' => 'in_progress',
            ]);

            $this->broadcastHandStarted($hand);
            $this->scheduleAutoFold($hand);

            return $hand->fresh(['players']);
        });
    }

    /**
     * Dispatch a player action.
     */
    public function playerAction(Hand $hand, User $user, string $action, array $data = []): Hand
    {
        return DB::transaction(function () use ($hand, $user, $action, $data) {
            $hand = Hand::lockForUpdate()->findOrFail($hand->id);
            if ($hand->status === 'completed') {
                throw new RuntimeException('Hand already completed.');
            }

            // Backstop in case the queue worker is down: fold any player whose
            // turn deadline has already passed before processing this action.
            $this->enforceExpiredTurns($hand);
            if ($hand->status === 'completed') {
                throw new RuntimeException('Hand already completed.');
            }

            $player = HandPlayer::where('hand_id', $hand->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($player->has_packed) {
                throw new RuntimeException('You have packed and cannot act.');
            }

            // sideshow_respond and pack can be required out-of-turn for sideshow target
            $isSideshowTarget = $hand->sideshow_target_id === $user->id && $action === 'sideshow_respond';
            if (! $isSideshowTarget) {
                if ($action !== 'see_cards' && $player->seat_position !== $hand->current_turn_seat) {
                    throw new RuntimeException('Not your turn.');
                }
            }

            switch ($action) {
                case 'see_cards':
                    $this->actionSeeCards($hand, $player);
                    break;
                case 'pack':
                    $this->actionPack($hand, $player);
                    break;
                case 'chaal':
                    $this->actionChaal($hand, $player, (float) ($data['amount'] ?? 0));
                    break;
                case 'sideshow_request':
                    $this->actionSideshowRequest($hand, $player);
                    break;
                case 'sideshow_respond':
                    $this->actionSideshowRespond($hand, $player, (bool) ($data['accept'] ?? false));
                    break;
                case 'show':
                    $this->actionShow($hand, $player);
                    break;
                default:
                    throw new RuntimeException("Unknown action: {$action}");
            }

            return $hand->fresh(['players', 'table']);
        });
    }

    protected function actionSeeCards(Hand $hand, HandPlayer $player): void
    {
        $player->update(['is_blind' => false]);
        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $player->user_id,
            'action' => 'see_cards',
            'amount' => 0,
            'was_blind' => true,
        ]);

        $this->broadcastPlayerActed($hand, $player, 'see_cards', 0, 0, true);
    }

    protected function actionPack(Hand $hand, HandPlayer $player): void
    {
        $player->update(['has_packed' => true]);
        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $player->user_id,
            'action' => 'pack',
            'amount' => 0,
            'was_blind' => $player->is_blind,
        ]);

        $this->broadcastPlayerActed($hand, $player, 'pack', 0, 0, $player->is_blind);

        $this->afterTurn($hand);
    }

    protected function actionChaal(Hand $hand, HandPlayer $player, float $amount): void
    {
        if (! $this->rules->validateBet($hand, $player, $amount)) {
            [$low, $high] = $this->rules->legalRange($hand, $player);
            $mode = $player->is_blind ? 'blind' : 'seen';
            throw new RuntimeException(
                "Your chaal must be between ₹{$low} and ₹{$high} while playing {$mode}. You entered ₹{$amount}."
            );
        }

        $user = $player->user;
        if ((float) $user->wallet_balance < $amount) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        $this->debitUser($user, $amount, 'bet', $hand->id);
        $player->increment('total_contributed', $amount);
        $hand->increment('pot_amount', $amount);
        $hand->update(['current_stake' => $this->rules->nextStake($player, $amount)]);

        $wasBlind = $player->is_blind;
        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $player->user_id,
            'action' => $wasBlind ? 'blind_bet' : 'chaal',
            'amount' => $amount,
            'was_blind' => $wasBlind,
        ]);

        $this->broadcastPlayerActed($hand, $player, $wasBlind ? 'blind_bet' : 'chaal', $amount, $amount, $wasBlind);

        $this->afterTurn($hand);
    }

    protected function actionSideshowRequest(Hand $hand, HandPlayer $player): void
    {
        if ($player->is_blind) {
            throw new RuntimeException('Only seen players can request a sideshow.');
        }

        $active = $this->activePlayers($hand);
        if ($active->count() < 3) {
            throw new RuntimeException('Sideshow requires at least 3 active players.');
        }

        $prev = $this->previousActivePlayer($hand, $player->seat_position);
        if (! $prev || $prev->is_blind) {
            throw new RuntimeException('Previous player must be seen.');
        }

        // Sideshow costs at minimum a "seen chaal" - player must pay
        $amount = (float) $hand->current_stake * 2;
        $user = $player->user;
        if ((float) $user->wallet_balance < $amount) {
            throw new RuntimeException('Insufficient wallet balance for sideshow.');
        }
        $this->debitUser($user, $amount, 'bet', $hand->id);
        $player->increment('total_contributed', $amount);
        $hand->increment('pot_amount', $amount);

        $hand->update([
            'sideshow_requester_id' => $player->user_id,
            'sideshow_target_id' => $prev->user_id,
        ]);

        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $player->user_id,
            'action' => 'sideshow_request',
            'amount' => $amount,
            'was_blind' => false,
        ]);

        $this->broadcastPlayerActed($hand, $player, 'sideshow_request', $amount, $amount, false);
    }

    protected function actionSideshowRespond(Hand $hand, HandPlayer $responder, bool $accept): void
    {
        if (! $hand->sideshow_target_id || $hand->sideshow_target_id !== $responder->user_id) {
            throw new RuntimeException('No sideshow pending for you.');
        }

        $requester = HandPlayer::where('hand_id', $hand->id)
            ->where('user_id', $hand->sideshow_requester_id)
            ->firstOrFail();

        if (! $accept) {
            Bet::create([
                'hand_id' => $hand->id,
                'user_id' => $responder->user_id,
                'action' => 'sideshow_decline',
                'amount' => 0,
                'was_blind' => $responder->is_blind,
            ]);
            $hand->update(['sideshow_requester_id' => null, 'sideshow_target_id' => null]);
            $this->broadcastPlayerActed($hand, $responder, 'sideshow_decline', 0, 0, $responder->is_blind);
            $this->afterTurn($hand);

            return;
        }

        // Accept: compare hands. Lower folds. Tie → requester folds.
        $reqEval = $this->evaluator->evaluate($this->cardsOf($requester));
        $respEval = $this->evaluator->evaluate($this->cardsOf($responder));
        $cmp = $this->comparator->compare($reqEval, $respEval);

        if ($cmp > 0) {
            $responder->update(['has_packed' => true, 'hand_rank' => $respEval['rank']]);
        } else {
            // tie or responder wins → requester folds
            $requester->update(['has_packed' => true, 'hand_rank' => $reqEval['rank']]);
        }

        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $responder->user_id,
            'action' => 'sideshow_accept',
            'amount' => 0,
            'was_blind' => false,
        ]);

        $hand->update(['sideshow_requester_id' => null, 'sideshow_target_id' => null]);
        $this->broadcastPlayerActed($hand, $responder, 'sideshow_accept', 0, 0, false);
        $this->afterTurn($hand);
    }

    protected function actionShow(Hand $hand, HandPlayer $player): void
    {
        $active = $this->activePlayers($hand);
        if ($active->count() !== 2) {
            throw new RuntimeException('Show is only allowed with exactly 2 players remaining.');
        }

        /** @var HandPlayer $opponent */
        $opponent = $active->first(fn (HandPlayer $hp) => $hp->user_id !== $player->user_id);

        $cost = $this->rules->showCost($hand, $player, $opponent);
        $user = $player->user;
        if ((float) $user->wallet_balance < $cost) {
            throw new RuntimeException('Insufficient wallet balance to show.');
        }

        $this->debitUser($user, $cost, 'bet', $hand->id);
        $player->increment('total_contributed', $cost);
        $hand->increment('pot_amount', $cost);

        $this->broadcastPlayerActed($hand, $player, 'show', $cost, $cost, $player->is_blind);

        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $player->user_id,
            'action' => 'show',
            'amount' => $cost,
            'was_blind' => $player->is_blind,
        ]);

        $hand->update(['status' => 'showdown']);

        $pEval = $this->evaluator->evaluate($this->cardsOf($player));
        $oEval = $this->evaluator->evaluate($this->cardsOf($opponent));
        $cmp = $this->comparator->compare($pEval, $oEval);

        $player->update(['hand_rank' => $pEval['rank']]);
        $opponent->update(['hand_rank' => $oEval['rank']]);

        if ($cmp > 0) {
            $this->endHand($hand, $player->user);
        } elseif ($cmp < 0) {
            $this->endHand($hand, $opponent->user);
        } else {
            // Tie — split pot
            $this->endHandSplit($hand, [$player, $opponent]);
        }
    }

    /**
     * Called after any action that consumes a turn.
     * Advances turn or auto-resolves the hand when only one active player remains.
     */
    protected function afterTurn(Hand $hand): void
    {
        $active = $this->activePlayers($hand);
        if ($active->count() === 1) {
            $winner = $active->first();
            $this->endHand($hand, $winner->user);

            return;
        }

        $next = $this->nextSeatFrom(
            $hand->current_turn_seat,
            $active->pluck('seat_position')->all()
        );
        $this->startTurn($hand, $next);
        $this->broadcastTurnChanged($hand);
        $this->scheduleAutoFold($hand);
    }

    public function endHand(Hand $hand, ?User $winner): void
    {
        if ($winner) {
            $this->creditUser($winner, (float) $hand->pot_amount, 'win', $hand->id);
            HandPlayer::where('hand_id', $hand->id)
                ->where('user_id', $winner->id)
                ->update(['is_winner' => true]);
        }

        $payouts = $winner
            ? [['user_id' => $winner->id, 'amount' => (string) $hand->pot_amount]]
            : [];

        $hand->update([
            'winner_user_id' => $winner?->id,
            'status' => 'completed',
            'ended_at' => now(),
            'current_turn_seat' => null,
            'turn_started_at' => null,
            'turn_deadline' => null,
        ]);

        $table = PokerTable::find($hand->poker_table_id);
        if ($table) {
            $table->update(['current_hand_id' => null, 'status' => 'waiting']);
        }

        $this->broadcastHandCompleted($hand, $payouts, isSplit: false);
    }

    protected function endHandSplit(Hand $hand, array $players): void
    {
        $share = (float) $hand->pot_amount / count($players);
        $payouts = [];
        foreach ($players as $hp) {
            /** @var HandPlayer $hp */
            $this->creditUser($hp->user, $share, 'win', $hand->id);
            $hp->update(['is_winner' => true]);
            $payouts[] = ['user_id' => $hp->user_id, 'amount' => (string) $share];
        }
        $hand->update([
            'winner_user_id' => null,
            'status' => 'completed',
            'ended_at' => now(),
            'current_turn_seat' => null,
            'turn_started_at' => null,
            'turn_deadline' => null,
        ]);
        $table = PokerTable::find($hand->poker_table_id);
        if ($table) {
            $table->update(['current_hand_id' => null, 'status' => 'waiting']);
        }

        $this->broadcastHandCompleted($hand, $payouts, isSplit: true);
    }

    protected function collectBoot(Hand $hand, HandPlayer $hp, float $amount): void
    {
        $user = $hp->user;
        if ((float) $user->wallet_balance < $amount) {
            throw new RuntimeException("User {$user->id} has insufficient balance for boot.");
        }
        $this->debitUser($user, $amount, 'bet', $hand->id);
        $hp->increment('total_contributed', $amount);
        $hand->increment('pot_amount', $amount);

        Bet::create([
            'hand_id' => $hand->id,
            'user_id' => $user->id,
            'action' => 'boot',
            'amount' => $amount,
            'was_blind' => true,
        ]);
    }

    protected function debitUser(User $user, float $amount, string $type, int $refId): void
    {
        $user->refresh();
        $new = (float) $user->wallet_balance - $amount;
        if ($new < 0) {
            throw new RuntimeException('Negative balance not allowed.');
        }
        $user->update(['wallet_balance' => $new]);
        Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => -$amount,
            'balance_after' => $new,
            'reference_type' => 'hand',
            'reference_id' => $refId,
        ]);
    }

    protected function creditUser(User $user, float $amount, string $type, int $refId): void
    {
        $user->refresh();
        $new = (float) $user->wallet_balance + $amount;
        $user->update(['wallet_balance' => $new]);
        Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $new,
            'reference_type' => 'hand',
            'reference_id' => $refId,
        ]);
    }

    protected function activePlayers(Hand $hand)
    {
        return HandPlayer::where('hand_id', $hand->id)
            ->where('has_packed', false)
            ->orderBy('seat_position')
            ->get();
    }

    protected function previousActivePlayer(Hand $hand, int $fromSeat): ?HandPlayer
    {
        $active = $this->activePlayers($hand);
        $seats = $active->pluck('seat_position')->sort()->values()->all();
        if (empty($seats)) {
            return null;
        }
        // counter-clockwise = decreasing seat number, wrap to highest below
        $candidates = array_filter($seats, fn ($s) => $s < $fromSeat);
        $prevSeat = empty($candidates) ? max($seats) : max($candidates);
        if ($prevSeat === $fromSeat) {
            return null;
        }

        return $active->firstWhere('seat_position', $prevSeat);
    }

    /**
     * Find the next active seat after the given seat. Wraps around.
     *
     * @param  array<int,int>  $availableSeats
     */
    protected function nextSeatFrom(?int $fromSeat, array $availableSeats): int
    {
        sort($availableSeats);
        if ($fromSeat === null) {
            return $availableSeats[0];
        }
        foreach ($availableSeats as $s) {
            if ($s > $fromSeat) {
                return $s;
            }
        }

        return $availableSeats[0];
    }

    /**
     * @return array<int, Card>
     */
    protected function cardsOf(HandPlayer $player): array
    {
        return array_map(fn (array $c) => Card::fromArray($c), $player->cards ?? []);
    }

    /**
     * Ordered list of seats starting at $startSeat, following the engine's
     * seat rotation (increasing seat, wrapping to the lowest).
     *
     * @param  array<int,int>  $seats
     * @return array<int,int>
     */
    protected function seatOrderFrom(int $startSeat, array $seats): array
    {
        sort($seats);
        $order = [];
        $seat = $startSeat;
        for ($i = 0, $n = count($seats); $i < $n; $i++) {
            $order[] = $seat;
            $seat = $this->nextSeatFrom($seat, $seats);
        }

        return $order;
    }

    protected function turnSeconds(): int
    {
        return (int) config('game.turn_seconds', 20);
    }

    /**
     * Point the turn at a seat and (re)start its countdown clock.
     */
    protected function startTurn(Hand $hand, int $seat): void
    {
        $hand->update([
            'current_turn_seat' => $seat,
            'turn_started_at' => now(),
            'turn_deadline' => now()->addSeconds($this->turnSeconds()),
        ]);
    }

    /**
     * Bump and return the per-hand monotonic action sequence. Stamped on every
     * broadcast event so the client can order/dedupe and detect gaps.
     */
    protected function nextSeq(Hand $hand): int
    {
        $hand->increment('action_seq');

        return (int) $hand->action_seq;
    }

    /**
     * Auto-fold (pack) a player whose turn timer expired. Public so the
     * AutoFoldTurn job and the in-request backstop can both reuse the exact
     * same bookkeeping as a manual pack.
     */
    public function autoFold(Hand $hand, HandPlayer $player): void
    {
        $this->actionPack($hand, $player);
    }

    /**
     * Backstop for a downed queue worker: before processing an action, fold any
     * player whose turn deadline has already passed. Bounded to avoid looping;
     * each fold resets the next player's clock to the future, so this normally
     * folds at most the one overdue player.
     */
    protected function enforceExpiredTurns(Hand $hand): void
    {
        $guard = 0;
        while (
            $hand->status === 'betting'
            && $hand->turn_deadline !== null
            && now()->greaterThanOrEqualTo($hand->turn_deadline)
            && $guard++ < 12
        ) {
            $current = HandPlayer::where('hand_id', $hand->id)
                ->where('seat_position', $hand->current_turn_seat)
                ->first();
            if (! $current || $current->has_packed) {
                break;
            }
            $this->autoFold($hand, $current);
            $hand->refresh();
        }
    }

    protected function scheduleAutoFold(Hand $hand): void
    {
        if ($hand->turn_deadline === null || $hand->current_turn_seat === null) {
            return;
        }

        AutoFoldTurn::dispatch(
            $hand->id,
            (int) $hand->current_turn_seat,
            $hand->turn_deadline->toIso8601String(),
        )->delay($hand->turn_deadline)->afterCommit();
    }

    protected function broadcastHandStarted(Hand $hand): void
    {
        $hand->load('players');

        HandStarted::dispatch($hand->table->code, [
            'hand_id' => $hand->id,
            'action_seq' => $this->nextSeq($hand),
            'dealer_seat' => $hand->dealer_seat,
            'previous_dealer_seat' => $hand->previous_dealer_seat,
            'current_turn_seat' => $hand->current_turn_seat,
            'turn_started_at' => optional($hand->turn_started_at)->toIso8601String(),
            'turn_deadline' => optional($hand->turn_deadline)->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'pot_amount' => (string) $hand->pot_amount,
            'current_stake' => (string) $hand->current_stake,
            'status' => $hand->status,
            'deal_order' => $hand->deal_order,
            'players' => $hand->players->map(fn (HandPlayer $hp) => [
                'user_id' => $hp->user_id,
                'seat_position' => $hp->seat_position,
                'is_blind' => $hp->is_blind,
                'has_packed' => $hp->has_packed,
                'total_contributed' => (string) $hp->total_contributed,
            ])->values()->all(),
        ]);
    }

    protected function broadcastPlayerActed(
        Hand $hand,
        HandPlayer $player,
        string $action,
        float $amount,
        float $potDelta,
        bool $wasBlind,
    ): void {
        PlayerActed::dispatch($hand->table->code, [
            'hand_id' => $hand->id,
            'action_seq' => $this->nextSeq($hand),
            'action' => $action,
            'user_id' => $player->user_id,
            'seat_position' => $player->seat_position,
            'amount' => (string) $amount,
            'pot_delta' => (string) $potDelta,
            'pot_amount' => (string) $hand->pot_amount,
            'current_stake' => (string) $hand->current_stake,
            'was_blind' => $wasBlind,
            'server_now' => now()->toIso8601String(),
        ]);
    }

    protected function broadcastTurnChanged(Hand $hand): void
    {
        TurnChanged::dispatch($hand->table->code, [
            'hand_id' => $hand->id,
            'action_seq' => $this->nextSeq($hand),
            'current_turn_seat' => $hand->current_turn_seat,
            'turn_started_at' => optional($hand->turn_started_at)->toIso8601String(),
            'turn_deadline' => optional($hand->turn_deadline)->toIso8601String(),
            'server_now' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, array{user_id:int, amount:string}>  $payouts
     */
    protected function broadcastHandCompleted(Hand $hand, array $payouts, bool $isSplit): void
    {
        $hand->load('players');

        // Only reveal cards for players who reached a genuine showdown
        // (hand_rank set). A fold-out winner's cards stay hidden.
        $revealed = $hand->players
            ->filter(fn (HandPlayer $hp) => $hp->hand_rank !== null)
            ->map(fn (HandPlayer $hp) => [
                'user_id' => $hp->user_id,
                'seat_position' => $hp->seat_position,
                'hand_rank' => $hp->hand_rank,
                'cards' => $hp->cards,
            ])->values()->all();

        HandCompleted::dispatch($hand->table->code, [
            'hand_id' => $hand->id,
            'action_seq' => $this->nextSeq($hand),
            'winner_user_id' => $hand->winner_user_id,
            'is_split' => $isSplit,
            'pot_amount' => (string) $hand->pot_amount,
            'payouts' => $payouts,
            'revealed' => $revealed,
            'server_now' => now()->toIso8601String(),
        ]);
    }
}
