<?php

namespace App\Modules\Product\Service;

use App\Modules\Organization\Domain\Organization;
use App\Modules\Product\Domain\Product;
use App\Modules\Product\Repository\ProductRepository;
use App\Shared\Exceptions\BusinessConflictException;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    private ProductRepository $repository;

    public function __construct(ProductRepository $repository) {
        $this->repository = $repository;
    }

    public function createProduct(
        Organization $organization,
        string $code,
        string $name,
        string $description,
        float $salePrice,
        float $costPrice,
    ): Product {
        $code = trim($code);

        if ($this->repository->existsByCodeInOrganization($code, $organization->id)) {
            throw new BusinessConflictException(
                "A product with code '{$code}' already exists in this organization."
            );
        }

        return $this->repository->saveReturn(Product::at(
            $organization,
            $code,
            $name,
            $description,
            $salePrice,
            $costPrice,
        ));
    }

    public function paginateByOrganization(
        int $organizationId,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->repository->paginateByOrganization($organizationId, $perPage);
    }
}
