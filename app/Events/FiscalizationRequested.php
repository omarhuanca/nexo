<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FiscalizationRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $organizationId,
        public readonly string $taskId,
        public readonly int    $saleId,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly array  $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("agent.{$this->organizationId}")];
    }

    public function broadcastAs(): string
    {
        return 'fiscalization.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'sale_id' => $this->saleId,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'payload' => $this->payload,
        ];
    }
}
