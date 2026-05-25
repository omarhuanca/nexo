<?php

namespace App\Modules\Integration\Domain;

use App\Shared\Domain\BaseEntity;

class IntegrationCredential extends BaseEntity
{
    protected $table =
        'integration_credentials';

    protected $fillable = [
        'integration_id',
        'credentials',
        'expires_at',
    ];

    protected $casts = [
        'credentials' => 'array',
        'expires_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}