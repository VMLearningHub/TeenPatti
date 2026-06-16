<?php

use App\Events\HandCompleted;
use App\Events\HandStarted;
use App\Events\PlayerActed;
use App\Events\TurnChanged;
use App\Jobs\AutoFoldTurn;
use App\Models\HandPlayer;
use App\Models\User;
use App\Services\Game\GameService;
use App\Services\Game\TableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Spin up a 2-player table and deal a hand. Returns [table, hand, [users...]].
 */
function seatTwoAndDeal(): array
{
    $tables = app(TableService::class);
    $game = app(GameService::class);

    $owner = User::factory()->create(['wallet_balance' => 1000]);
    $other = User::factory()->create(['wallet_balance' => 1000]);

    $table = $tables->create($owner, ['boot_amount' => 10, 'min_bet' => 10, 'max_bet' => 100]);
    $tables->join($table, $other, 500);

    $hand = $game->startHand($table->fresh());

    return [$table, $hand, [$owner, $other]];
}

it('broadcasts HandStarted with deal order and a sequence but no hole cards', function () {
    Event::fake();

    [, $hand] = seatTwoAndDeal();

    Event::assertDispatched(HandStarted::class, function (HandStarted $e) {
        expect(json_encode($e->payload))->not->toContain('"cards"');
        expect($e->payload['deal_order'])->toBeArray()->not->toBeEmpty();
        expect($e->payload['action_seq'])->toBeGreaterThan(0);
        expect($e->payload['previous_dealer_seat'])->not->toBeNull();
        expect($e->payload['turn_deadline'])->not->toBeNull();

        return true;
    });
});

it('never leaks cards on PlayerActed and hides a fold-out winner', function () {
    Event::fake();

    [, $hand] = seatTwoAndDeal();

    // The player on turn packs → the other wins by fold-out (no showdown).
    $seat = $hand->current_turn_seat;
    $actor = User::find(
        HandPlayer::where('hand_id', $hand->id)->where('seat_position', $seat)->value('user_id')
    );

    app(GameService::class)->playerAction($hand, $actor, 'pack');

    Event::assertDispatched(PlayerActed::class, function (PlayerActed $e) {
        expect(json_encode($e->payload))->not->toContain('"cards"');

        return $e->payload['action'] === 'pack';
    });

    Event::assertDispatched(HandCompleted::class, function (HandCompleted $e) {
        // Fold-out: no genuine showdown, so no revealed cards at all.
        expect($e->payload['revealed'])->toBe([]);
        expect($e->payload['winner_user_id'])->not->toBeNull();

        return true;
    });
});

it('keeps TurnChanged free of card data', function () {
    Event::fake();

    [, $hand] = seatTwoAndDeal();

    // A blind chaal by the player on turn advances the turn in a 2-player hand.
    $seat = $hand->current_turn_seat;
    $actor = User::find(
        HandPlayer::where('hand_id', $hand->id)->where('seat_position', $seat)->value('user_id')
    );

    app(GameService::class)->playerAction($hand, $actor, 'chaal', ['amount' => 10]);

    Event::assertDispatched(TurnChanged::class, function (TurnChanged $e) {
        expect(json_encode($e->payload))->not->toContain('"cards"');
        expect($e->payload['turn_deadline'])->not->toBeNull();

        return true;
    });
});

it('auto-folds the player whose turn deadline has passed', function () {
    Event::fake();

    [, $hand] = seatTwoAndDeal();

    $seat = $hand->current_turn_seat;
    $hand->update(['turn_deadline' => now()->subSeconds(2)]);

    (new AutoFoldTurn($hand->id, $seat, $hand->fresh()->turn_deadline->toIso8601String()))
        ->handle(app(GameService::class));

    $player = HandPlayer::where('hand_id', $hand->id)->where('seat_position', $seat)->first();
    expect($player->has_packed)->toBeTrue();
});

it('does not auto-fold when the turn has already advanced (stale job no-ops)', function () {
    Event::fake();

    [, $hand] = seatTwoAndDeal();

    $seat = $hand->current_turn_seat;
    // Schedule a job for the current seat, but the deadline is still in the future.
    (new AutoFoldTurn($hand->id, $seat, $hand->fresh()->turn_deadline->toIso8601String()))
        ->handle(app(GameService::class));

    $player = HandPlayer::where('hand_id', $hand->id)->where('seat_position', $seat)->first();
    expect($player->has_packed)->toBeFalse();
});
