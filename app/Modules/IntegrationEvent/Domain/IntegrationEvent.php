<?php

namespace App\Modules\IntegrationEvent\Domain;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegrationEvent extends BaseEntity
{
    use HasFactory, GettersAndSetters;
    protected $table = 'integration_events';

    protected $fillable = [
        'external_event_id',
        'organization_id',
        'connector_id',

        'event_type',
        'payload',
        'status',
        'attempts',

        'processed_at',
        'error_message',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }

    public function markAsProcessing(): void
    {
        $this->status = 'processing';
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->processed_at = now();
        $this->error_message = null;
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->attempts++;
    }

    public function canRetry(int $maxAttempts = 5): bool
    {
        return $this->status === 'failed' && $this->attempts < $maxAttempts;
    }
}