<?php

namespace App\Modules\Agent\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;

class AgentToken extends BaseEntity
{
    use GettersAndSetters;

    protected $table = 'agent_tokens';

    protected $fillable = [
        'organization_id',
        'token_hash',
        'name',
        'last_seen_at',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
