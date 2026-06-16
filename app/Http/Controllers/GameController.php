<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Hand;
use App\Models\HandPlayer;
use App\Models\PokerTable;
use App\Services\Game\BettingRules;
use App\Services\Game\GameService;
use App\Services\Game\TableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GameController extends Controller
{
    public function __construct(
        protected GameService $game,
        protected TableService $tables,
        protected BettingRules $rules,
    ) {}

    public function show(PokerTable $table): Response
    {
        $table->load([
            'tablePlayers.user:id,name,wallet_balance,avatar',
            'currentHand.players',
        ]);

        $userId = Auth::id();

        // Use the in-progress hand if there is one, otherwise fall back to the
        // most recent hand so the completed showdown (winner + revealed cards)
        // stays visible until a new hand is dealt.
        $hand = $table->currentHand
            ?? Hand::with('players')
                ->where('poker_table_id', $table->id)
                ->latest('id')
                ->first();

        $myHand = null;
        $myLegalRange = null;
        if ($hand) {
            $myHandPlayer = $hand->players->firstWhere('user_id', $userId);
            if ($myHandPlayer) {
                // While playing blind the player must NOT see their own cards —
                // they have to click "See Cards" first (which sets is_blind = false).
                // Cards stay revealed once seen, or after a real showdown
                // (hand_rank is only set when cards are actually compared).
                $reveal = ! $myHandPlayer->is_blind
                    || $myHandPlayer->hand_rank !== null;
                $myHand = $reveal ? $myHandPlayer->cards : null;
                $myLegalRange = $this->rules->legalRange($hand, $myHandPlayer);
            }
        }

        $publicHand = null;
        if ($hand) {
            $publicHand = [
                'id' => $hand->id,
                'status' => $hand->status,
                'pot_amount' => $hand->pot_amount,
                'current_stake' => $hand->current_stake,
                'current_turn_seat' => $hand->current_turn_seat,
                'dealer_seat' => $hand->dealer_seat,
                'previous_dealer_seat' => $hand->previous_dealer_seat,
                'sideshow_requester_id' => $hand->sideshow_requester_id,
                'sideshow_target_id' => $hand->sideshow_target_id,
                'winner_user_id' => $hand->winner_user_id,
                // Real-time / animation enrichments — kept in sync with the
                // broadcast events so polling and sockets converge.
                'deal_order' => $hand->deal_order,
                'action_seq' => $hand->action_seq,
                'turn_started_at' => optional($hand->turn_started_at)->toIso8601String(),
                'turn_deadline' => optional($hand->turn_deadline)->toIso8601String(),
                'server_now' => now()->toIso8601String(),
                'recent_actions' => Bet::where('hand_id', $hand->id)
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (Bet $b) => [
                        'id' => $b->id,
                        'user_id' => $b->user_id,
                        'action' => $b->action,
                        'amount' => (string) $b->amount,
                        'created_at' => optional($b->created_at)->toIso8601String(),
                    ])
                    ->values(),
                'players' => $hand->players->map(function (HandPlayer $hp) use ($userId) {
                    return [
                        'user_id' => $hp->user_id,
                        'seat_position' => $hp->seat_position,
                        'is_blind' => $hp->is_blind,
                        'has_packed' => $hp->has_packed,
                        'is_winner' => $hp->is_winner,
                        'hand_rank' => $hp->hand_rank,
                        'total_contributed' => $hp->total_contributed,
                        // Reveal cards only at a genuine showdown (hand_rank is
                        // set when cards are compared in a show/sideshow), or to
                        // yourself once you've seen them. A player who wins because
                        // everyone else packed never shows their cards.
                        'cards' => (($hp->user_id === $userId && ! $hp->is_blind) || $hp->hand_rank !== null)
                            ? $hp->cards
                            : null,
                    ];
                }),
            ];
        }

        return Inertia::render('Game/Show', [
            'table' => $table,
            'hand' => $publicHand,
            'my_cards' => $myHand,
            'my_legal_range' => $myLegalRange,
            'me' => Auth::user()->only(['id', 'name', 'wallet_balance']),
        ]);
    }

    public function start(PokerTable $table)
    {
        try {
            $this->game->startHand($table);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function action(Request $request, PokerTable $table)
    {
        $data = $request->validate([
            'action' => 'required|string|in:see_cards,pack,chaal,sideshow_request,sideshow_respond,show',
            'amount' => 'nullable|numeric|min:0',
            'accept' => 'nullable|boolean',
        ]);

        $hand = Hand::where('poker_table_id', $table->id)
            ->where('status', '!=', 'completed')
            ->latest('id')->firstOrFail();

        try {
            $this->game->playerAction($hand, Auth::user(), $data['action'], $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function leave(PokerTable $table)
    {
        $this->tables->leave($table, Auth::user());

        return redirect()->route('lobby');
    }

    public function state(PokerTable $table)
    {
        // JSON endpoint used by polling
        return $this->show($table)->toResponse(request());
    }
}
