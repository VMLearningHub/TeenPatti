<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new hand has been dealt. Payload carries the public hand snapshot plus the
 * deal order and dealer movement — never any hole cards.
 */
class HandStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'HandStarted';
    }

    /**
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
