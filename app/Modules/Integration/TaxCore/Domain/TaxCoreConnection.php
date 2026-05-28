<?php

namespace App\Modules\Integration\TaxCore\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;

class TaxCoreConnection extends BaseEntity
{
    use GettersAndSetters;

    protected $table = 'taxcore_connections';

    protected $fillable = [
        'organization_id',
        'certificate_encrypted',
        'certificate_password_encrypted',
        'pac_encrypted',
        'environment',
        'vsdc_url',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}