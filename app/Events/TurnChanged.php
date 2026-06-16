<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The turn advanced to a new seat. Payload carries the new seat and the turn
 * deadline so the client can render/resync the countdown ring.
 */
class TurnChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'TurnChanged';
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
