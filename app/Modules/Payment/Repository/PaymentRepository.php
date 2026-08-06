<?php

namespace App\Modules\Payment\Repository;

use App\Modules\Payment\Domain\Payment;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentRepository extends AbstractRepository
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function findBySaleId(int $saleId): Collection
    {
        return $this->model
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->get();
    }

    public function paginateBySale(int $saleId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findByPaymentType(int $saleId, int $paymentType): ?Payment
    {
        return $this->model
            ->where('sale_id', $saleId)
            ->where('payment_type', $paymentType)
            ->first();
    }
}
