<?php

namespace App\Modules\Sale\Service;

use App\Events\Sale\SaleSubmitted;
use App\Jobs\ProcessSaleJob;
use App\Modules\Buyer\Service\BuyerService;
use App\Modules\Connector\Domain\Connector;
use App\Modules\LineItem\Service\LineItemService;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Product\Repository\ProductRepository;
use App\Modules\Sale\Domain\ListSalesCriteria;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleService
{
    private readonly SaleRepository $saleRepository;
    private readonly BuyerService $buyerService;
    private readonly LineItemService $lineItemService;
    private readonly PaymentService $paymentService;
    private readonly ProductRepository $productRepository;

    public function __construct(
        SaleRepository $saleRepository,
        BuyerService $buyerService,
        LineItemService $lineItemService,
        PaymentService $paymentService,
        ProductRepository $productRepository,
    ) {
        $this->saleRepository = $saleRepository;
        $this->buyerService = $buyerService;
        $this->lineItemService = $lineItemService;
        $this->paymentService = $paymentService;
        $this->productRepository = $productRepository;
    }

    public function createSale(Connector $connector, array $payload): Sale
    {
        $sale = Sale::fromPayload(
            $connector->getOrganizationId(),
            $connector->getId(),
            $payload,
        );

        $sale = $this->saleRepository->saveReturn($sale);

        $this->buyerService->createBuyerFromSale(
            $sale,
            $payload['buyer']['name'] ?? '',
            $payload['buyer']['id'] ?? null,
        );

        foreach ($payload['items'] as $rawItem) {
            $product = $this->productRepository->findByCodeInOrganization(
                $rawItem['code'],
                $connector->getOrganizationId()
            );

            $this->lineItemService->createLineItem(
                $sale,
                $product,
                (int) $rawItem['quantity'],
                (float) $rawItem['totalAmount'],
                $rawItem['labels'],
                $rawItem['accountCode'],
                $rawItem['gtin'] ?? null,
            );
        }

        foreach ($payload['payment'] as $rawPayment) {
            $this->paymentService->createPayment(
                $sale,
                (float) $rawPayment['amount'],
                (int) $rawPayment['paymentType'],
            );
        }

        $this->saleRepository->save($sale);

        event(new SaleSubmitted(
            $sale->getId(),
            $connector->getOrganizationId(),
            $connector->getId(),
            $payload,
        ));

        ProcessSaleJob::dispatch($sale->getId())->onQueue('sales');

        return $sale;
    }

    public function getSaleById(int $id, int $organizationId): Sale
    {
        return $this->saleRepository->findByIdForOrganization($id, $organizationId);
    }

    public function listInvoices(int $organizationId, ListSalesCriteria $criteria): LengthAwarePaginator
    {
        return $this->saleRepository->listForOrganization($organizationId, $criteria);
    }

    public function getInvoice(int $id, int $organizationId): Sale
    {
        return $this->getSaleById($id, $organizationId);
    }
}
