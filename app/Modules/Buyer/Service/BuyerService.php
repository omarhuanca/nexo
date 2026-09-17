<?php

namespace App\Modules\Buyer\Service;

use App\Modules\Buyer\Domain\Buyer;
use App\Modules\Buyer\Repository\BuyerRepository;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

class BuyerService
{
    private BuyerRepository $repository;

    public function __construct(BuyerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createBuyerFromSale(Sale $sale, string $name, ?string $documentNumber): Buyer
    {
        $buyer = Buyer::at($sale, $name, $documentNumber);

        return $this->repository->saveReturn($buyer);
    }

    public function getBuyerById(int $id): Buyer
    {
        return $this->repository->findById($id);
    }

    public function paginate(?int $saleId, ?string $name, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateFiltered($saleId, $name, $perPage);
    }
}
