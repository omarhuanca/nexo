<?php

namespace App\Modules\Sale\Domain;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends BaseEntity
{
    use HasFactory, GettersAndSetters;

    protected $table = 'sales';

    protected static function newFactory()
    {
        return \Database\Factories\SaleFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'connector_id',
        'status',
        'payload',
        'xero_invoice_id',
        'xero_result',
        'fiscal_number',
        'fiscal_result',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'xero_result'  => 'array',
        'fiscal_result' => 'array',
        'processed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }
}
