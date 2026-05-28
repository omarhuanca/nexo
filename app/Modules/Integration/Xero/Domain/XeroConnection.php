<?php

namespace App\Modules\Integration\Xero\Domain;

use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;

class XeroConnection extends BaseEntity
{
    use GettersAndSetters;
    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'tenant_type',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];
}
