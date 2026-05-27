<?php

namespace App\Http\Controllers;

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
        $hand = $table->currentHand;

        $myHand = null;
        $myLegalRange = null;
        if ($hand) {
            $myHandPlayer = $hand->players->firstWhere('user_id', $userId);
            if ($myHandPlayer) {
                $myHand = $myHandPlayer->cards;
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
                'sideshow_requester_id' => $hand->sideshow_requester_id,
                'sideshow_target_id' => $hand->sideshow_target_id,
                'winner_user_id' => $hand->winner_user_id,
                'players' => $hand->players->map(function (HandPlayer $hp) use ($userId) {
                    return [
                        'user_id' => $hp->user_id,
                        'seat_position' => $hp->seat_position,
                        'is_blind' => $hp->is_blind,
                        'has_packed' => $hp->has_packed,
                        'is_winner' => $hp->is_winner,
                        'hand_rank' => $hp->hand_rank,
                        'total_contributed' => $hp->total_contributed,
                        // Reveal cards only at showdown or for self
                        'cards' => ($hp->user_id === $userId || $hp->is_winner || $hp->hand_rank !== null)
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
        $this->game->startHand($table);

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

        $this->game->playerAction($hand, Auth::user(), $data['action'], $data);

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
