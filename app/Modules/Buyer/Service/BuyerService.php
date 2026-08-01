<?php

namespace App\Modules\Buyer\Service;

use App\Modules\Buyer\Domain\Buyer;
use App\Modules\Buyer\Repository\BuyerRepository;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Exceptions\NotFoundException;

class BuyerService
{
    private BuyerRepository $repository;

    public function __construct(BuyerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function createBuyer(Organization $organization, string $name, ?string $documentNumber): Buyer
    {
        $buyer = Buyer::at($organization, $name, $documentNumber);

        if ($documentNumber !== null && $this->repository->existsByDocumentNumberInOrganization($documentNumber, $organization->getId())) {
            throw new BusinessConflictException(
                "A buyer with document number '{$documentNumber}' already exists in this organization."
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

        $newDocumentNumber = $buyer->document_number;
        if (array_key_exists('document_number', $data)) {
            $raw = $data['document_number'];
            $newDocumentNumber = ($raw === null || trim((string) $raw) === '') ? null : trim((string) $raw);
        }

        Buyer::at($buyer->organization, $newName, $newDocumentNumber);

        if ($newDocumentNumber !== null && $this->repository->existsByDocumentNumberInOrganization(
            $newDocumentNumber,
            $buyer->organization_id,
            $buyer->getId()
        )) {
            throw new BusinessConflictException(
                "A buyer with document number '{$newDocumentNumber}' already exists in this organization."
            );
        }

        $buyer->name = $newName;
        $buyer->document_number = $newDocumentNumber;

        return $this->repository->saveReturn($buyer);
    }

    public function findOrCreateByDocumentNumber(Organization $organization, string $name, ?string $documentNumber): Buyer
    {
        if ($documentNumber !== null) {
            $existing = $this->repository->findByDocumentNumberForOrganization($documentNumber, $organization->getId());
            if ($existing) {
                return $existing;
            }
        }

        return $this->createBuyer($organization, $name, $documentNumber);
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
