<?php

namespace App\Modules\Integration\Domain\Xero;

use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Model;

class XeroConnection extends Model
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
