<?php

namespace App\Modules\LineItem\Service;

use App\Modules\LineItem\Domain\LineItem;
use App\Modules\LineItem\Repository\LineItemRepository;
use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\NotFoundException;

class LineItemService
{
    private LineItemRepository $repository;

    public function __construct(LineItemRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createLineItem(
        Sale $sale,
        string $code,
        string $name,
        float $quantity,
        float $unitPrice,
        float $totalAmount,
        array $labels,
        string $accountCode,
        ?string $gtin = null,
    ): LineItem {
        $item = LineItem::at(
            $sale,
            $code,
            $name,
            $quantity,
            $unitPrice,
            $totalAmount,
            $labels,
            $accountCode,
            $gtin,
        );

        return $this->repository->saveReturn($item);
    }

    public function updateLineItem(int $id, array $data): LineItem
    {
        $item = $this->repository->findById($id);

        $newCode = array_key_exists('code', $data)
            ? (string) $data['code']
            : $item->code;

        $newName = array_key_exists('name', $data)
            ? (string) $data['name']
            : $item->name;

        $newQuantity = array_key_exists('quantity', $data)
            ? (float) $data['quantity']
            : $item->quantity;

        $newUnitPrice = array_key_exists('unit_price', $data)
            ? (float) $data['unit_price']
            : $item->unit_price;

        $newTotalAmount = array_key_exists('total_amount', $data)
            ? (float) $data['total_amount']
            : $item->total_amount;

        $newLabels = array_key_exists('labels', $data)
            ? (array) $data['labels']
            : $item->labels;

        $newAccountCode = array_key_exists('account_code', $data)
            ? (string) $data['account_code']
            : $item->account_code;

        $newGtin = $item->gtin;
        if (array_key_exists('gtin', $data)) {
            $raw = $data['gtin'];
            $newGtin = ($raw === null || (string) $raw === '') ? null : (string) $raw;
        }

        LineItem::at(
            $item->sale,
            $newCode,
            $newName,
            $newQuantity,
            $newUnitPrice,
            $newTotalAmount,
            $newLabels,
            $newAccountCode,
            $newGtin,
        );

        $item->code = $newCode;
        $item->name = $newName;
        $item->quantity = $newQuantity;
        $item->unit_price = $newUnitPrice;
        $item->total_amount = $newTotalAmount;
        $item->labels = $newLabels;
        $item->account_code = $newAccountCode;
        $item->gtin = $newGtin === '' ? null : $newGtin;

        return $this->repository->saveReturn($item);
    }

    public function getLineItemById(int $id): LineItem
    {
        return $this->repository->findById($id);
    }

    public function paginateBySale(int $saleId, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginateBySale($saleId, $perPage);
    }

    public function deleteLineItem(int $id): void
    {
        $this->repository->findById($id);
        $this->repository->delete($id);
    }
}
