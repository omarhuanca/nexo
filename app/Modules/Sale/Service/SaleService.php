<?php

namespace App\Modules\Sale\Service;

use App\Events\Sale\SaleSubmitted;
use App\Jobs\ProcessSaleJob;
use App\Modules\Connector\Domain\Connector;
use App\Modules\Sale\Domain\ListSalesCriteria;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleService
{
    public function __construct(private readonly SaleRepository $saleRepository) {}

    public function createSale(Connector $connector, array $payload): Sale
    {
        $sale = Sale::fromPayload(
            $connector->getOrganizationId(),
            $connector->getId(),
            $payload,
        );

        $sale = $this->saleRepository->saveReturn($sale);

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
