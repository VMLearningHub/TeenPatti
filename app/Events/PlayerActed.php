<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A player took an action (see_cards, pack, chaal, blind_bet, sideshow_*, show).
 * Payload carries the action, the actor, and pot/stake deltas — never any cards.
 */
class PlayerActed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string,mixed>  $payload
     */
    public function __construct(
        public string $tableCode,
        public array $payload,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('table.'.$this->tableCode)];
    }

    public function broadcastAs(): string
    {
        return 'PlayerActed';
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
