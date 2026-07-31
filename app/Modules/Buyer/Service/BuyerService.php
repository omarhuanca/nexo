<?php

namespace App\Modules\Buyer\Service;

use App\Modules\Buyer\Domain\Buyer;
use App\Modules\Buyer\Repository\BuyerRepository;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Exceptions\NotFoundException;

class BuyerService
{
    public function __construct(private readonly BuyerRepository $repository) {}

    public function createBuyer(Organization $organization, string $name, ?string $taxId): Buyer
    {
        $buyer = Buyer::at($organization, $name, $taxId);

        if ($taxId !== null && $this->repository->existsByTaxIdInOrganization($taxId, $organization->getId())) {
            throw new BusinessConflictException(
                "A buyer with tax_id '{$taxId}' already exists in this organization."
            );
        }

        return $this->repository->saveReturn($buyer);
    }

    public function updateBuyer(int $id, array $data): Buyer
    {
        $buyer = $this->repository->findById($id);

        $newName = array_key_exists('name', $data)
            ? trim((string) ($data['name'] ?? ''))
            : $buyer->name;

        $newTaxId = $buyer->tax_id;
        if (array_key_exists('tax_id', $data)) {
            $raw = $data['tax_id'];
            $newTaxId = ($raw === null || trim((string) $raw) === '') ? null : trim((string) $raw);
        }

        Buyer::at($buyer->organization, $newName, $newTaxId);

        if ($newTaxId !== null && $this->repository->existsByTaxIdInOrganization(
            $newTaxId,
            $buyer->organization_id,
            $buyer->getId()
        )) {
            throw new BusinessConflictException(
                "A buyer with tax_id '{$newTaxId}' already exists in this organization."
            );
        }

        $buyer->name = $newName;
        $buyer->tax_id = $newTaxId;

        return $this->repository->saveReturn($buyer);
    }

    public function findOrCreateByTaxId(Organization $organization, string $name, ?string $taxId): Buyer
    {
        if ($taxId !== null) {
            $existing = $this->repository->findByTaxIdForOrganization($taxId, $organization->getId());
            if ($existing) {
                return $existing;
            }
        }

        return $this->createBuyer($organization, $name, $taxId);
    }

    public function getBuyerById(int $id): Buyer
    {
        return $this->repository->findById($id);
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->repository->paginateByOrganization($organizationId, $perPage);
    }

    public function deleteBuyer(int $id): void
    {
        $buyer = $this->repository->findById($id);

        if (! $buyer->active) {
            throw new BusinessConflictException("Buyer is already inactive.");
        }

        $this->repository->delete($id);
    }

    public function deactivateBuyer(int $id): Buyer
    {
        $buyer = $this->repository->findById($id);
        $buyer->active = false;
        return $this->repository->saveReturn($buyer);
    }

    public function activateBuyer(int $id): Buyer
    {
        $buyer = $this->repository->findById($id);
        $buyer->active = true;
        return $this->repository->saveReturn($buyer);
    }
}
