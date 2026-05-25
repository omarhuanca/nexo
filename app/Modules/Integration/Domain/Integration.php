<?php

namespace App\Modules\Integration\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;

class Integration extends BaseEntity
{
    protected $table = 'integrations';

    protected $fillable = [
        'organization_id',
        'provider',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function credential()
    {
        return $this->hasOne(IntegrationCredential::class);
    }
}