<?php

namespace App\Modules\Payment\Service;

use App\Modules\Payment\Domain\Payment;
use App\Modules\Payment\Repository\PaymentRepository;
use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\NotFoundException;

class PaymentService
{
    private PaymentRepository $repository;

    public function __construct(PaymentRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createPayment(Sale $sale, float $amount, int $paymentType, int $sequence = 0): Payment
    {
        $payment = Payment::at($sale, $amount, $paymentType, $sequence);

        return $this->repository->saveReturn($payment);
    }

    public function updatePayment(int $id, array $data): Payment
    {
        $payment = $this->repository->findById($id);

        $newAmount = array_key_exists('amount', $data)
            ? (float) ($data['amount'] ?? 0)
            : $payment->amount;

        $newPaymentType = array_key_exists('payment_type', $data)
            ? (int) ($data['payment_type'] ?? -1)
            : $payment->payment_type;

        $newSequence = array_key_exists('sequence', $data)
            ? (int) ($data['sequence'] ?? 0)
            : $payment->sequence;

        Payment::at($payment->sale, $newAmount, $newPaymentType, $newSequence);

        $payment->amount = $newAmount;
        $payment->payment_type = $newPaymentType;
        $payment->sequence = $newSequence;

        return $this->repository->saveReturn($payment);
    }

    public function getPaymentById(int $id): Payment
    {
        return $this->repository->findById($id);
    }

    public function paginateBySale(int $saleId, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginateBySale($saleId, $perPage);
    }

    public function deletePayment(int $id): void
    {
        $this->repository->findById($id);
        $this->repository->delete($id);
    }
}
