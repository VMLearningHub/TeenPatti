<?php

namespace App\Jobs;

use App\Models\Hand;
use App\Models\HandPlayer;
use App\Services\Game\GameService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the turn timer: if the seat we were scheduled for is still on the
 * clock at the deadline, auto-fold (pack) that player. Idempotent — a stale job
 * (turn already advanced, hand ended, or the deadline was reset) no-ops, so we
 * never need to cancel previously scheduled jobs.
 */
class AutoFoldTurn implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $handId,
        public int $seat,
        public string $deadlineIso,
    ) {}

    public function handle(GameService $game): void
    {
        DB::transaction(function () use ($game) {
            $hand = Hand::lockForUpdate()->find($this->handId);

            if (! $hand || $hand->status !== 'betting') {
                return; // hand ended
            }

            if ((int) $hand->current_turn_seat !== $this->seat) {
                return; // turn already advanced
            }

            if ($hand->turn_deadline === null) {
                return;
            }

            // Guard against a stale job for an earlier turn at this same seat:
            // only act if the row's deadline still matches the one we were
            // scheduled for (within a 1s tolerance) and has actually passed.
            if (abs($hand->turn_deadline->getTimestamp() - Carbon::parse($this->deadlineIso)->getTimestamp()) > 1) {
                return;
            }

            if (now()->lessThan($hand->turn_deadline)) {
                return; // not yet expired
            }

            $player = HandPlayer::where('hand_id', $hand->id)
                ->where('seat_position', $this->seat)
                ->first();

            if (! $player || $player->has_packed) {
                return;
            }

            $game->autoFold($hand, $player);
        });
    }
}
