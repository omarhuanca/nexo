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
    public ?string $plainCallbackSecret = null;

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
        'callback_url',
        'callback_secret',
        'last_used_at',
    ];

    protected $hidden = [
        'token',
        'callback_secret',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allowed_events' => 'array',
        'callback_secret' => 'encrypted',
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

    public function hasCallback(): bool
    {
        return $this->active && !empty($this->callback_url) && !empty($this->callback_secret);
    }

    public function generateCallbackSecret(): string
    {
        $plain = 'whsec_' . bin2hex(random_bytes(32));
        $this->callback_secret = $plain;
        $this->plainCallbackSecret = $plain;

        return $plain;
    }

    public function canSendEvent(string $eventType): bool
    {
        if (!$this->active) return false;
        if (is_null($this->allowed_events)) return true;

        return in_array($eventType, $this->allowed_events);
    }
}