<?php

namespace App\Modules\Connector\Domain;

use App\Modules\IntegrationEvent\Domain\IntegrationEvent;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connector extends BaseEntity
{
    use HasFactory, GettersAndSetters;
    protected $table = 'connectors';

    public ?string $plainToken = null;

    protected static function newFactory()
    {
        return \Database\Factories\ConnectorFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'name',
        'token',
        'active',
        'allowed_events',
        'last_used_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allowed_events' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationEvents(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class, 'connector_id');
    }

    public function canSendEvent(string $eventType): bool
    {
        if (!$this->active) return false;
        if (is_null($this->allowed_events)) return true;

        return in_array($eventType, $this->allowed_events);
    }
}