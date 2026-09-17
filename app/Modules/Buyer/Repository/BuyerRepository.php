<?php

namespace App\Modules\Buyer\Repository;

use App\Modules\Buyer\Domain\Buyer;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class BuyerRepository extends AbstractRepository
{
    public function __construct(Buyer $model)
    {
        parent::__construct($model);
    }

    public function paginateFiltered(?int $saleId, ?string $name, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if ($saleId !== null) {
            $query->where('sale_id', $saleId);
        }

        if ($name !== null && $name !== '') {
            $query->where('name', 'ilike', "%{$name}%");
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
