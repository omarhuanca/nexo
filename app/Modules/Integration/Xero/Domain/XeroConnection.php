<?php

namespace App\Modules\Integration\Xero\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;

class XeroConnection extends BaseEntity
{
    use GettersAndSetters;
    protected $fillable = [
        'organization_id',
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

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
