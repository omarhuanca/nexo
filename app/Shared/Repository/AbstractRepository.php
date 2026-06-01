<?php

namespace App\Shared\Repository;

use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Pagination\LengthAwarePaginator;
use \Illuminate\Database\QueryException;
use \Illuminate\Support\Collection;
use RuntimeException;

abstract class AbstractRepository
{
    // Common repository methods can be defined here

    protected Model $model;
    
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @param object $entity
     */
    public function save(object $entity): void
    {
        try {
            $entity->save();
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while saving entity.");
        }
    }

    public function saveReturn(object $entity): object
    {
        try {
            $entity->save();
            return $entity;
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while saving entity.");
        }
    }


    /**
     * Buscar entidad por ID
     *
     * @return object
     * @throws \RuntimeException Si la entidad no existe
     */
    public function findById(int $id): object
    {
        try {
            $entity = $this->model->find($id);
            
            if (!$entity) {
                throw new NotFoundException(
                    class_basename($this->model) . " not found with ID: {$id}"
                );
            }
            
            return $entity;
        } catch (NotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while finding entity.");
        }
    }

    /**
     * Buscar entidad por campo
     *
     * @param string $field
     * @param mixed $value
     * @return object
     * @throws \RuntimeException Si la entidad no existe
     */
    public function findBy(string $field, mixed $value): object
    {
        try {
            if (is_string($value)) {
                // Case-insensitive (sin distinción de mayúsculas)
                $entity = $this->model->whereRaw(
                    "LOWER({$field}) = LOWER(?)", 
                    [trim($value)]
                )->first();
            } else {
                $entity = $this->model->where($field, $value)->first();
            }
            
            if (!$entity) {
                throw new NotFoundException(
                    class_basename($this->model) . " not found."
                );
            }
            
            return $entity;
        } catch (NotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while finding entity.");
        }
    }

    /**
     * @return Collection<int, Model>
     */
    public function getAll()
    {
        try {
            return $this->model->all(); // Collection de objetos Eloquent
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while retrieving entities.");
        }
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        try {
            return $this->model->paginate($perPage);
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while paginating.");
        }
    }

    public function searchPaginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        try {
            $query = $this->model->query();
            if($search){ $search = mb_strtolower(trim($search));
            $query->whereRaw("LOWER(name) LIKE ?", ["%{$search}%"]); }
            return $query->paginate($perPage);
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while paginating.");
        }
    }


    /**
     * Contar entidades
     *
     * @return int
     */
    public function count(): int
    {
        try {
            return $this->model->count();
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while counting entities.");
        }
    }

    /**
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return int
     */
    public function countByDateRange(\DateTime $startDate, \DateTime $endDate): int
    {
        try {
            return $this->model
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while counting entities by date range.");
        }
    }
    /**
     * Verify state existence
     *
     * @param string $state
     * @return object
     */
    public function findByState(string $state): ?object
    {
        try {
            return $this->model->whereRaw(
                "LOWER(state) = LOWER(?)",
                [trim($state)]
            )->first();
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while finding entity by state.");
        }
    }

    /**
     * @param string $field Nombre del campo a buscar
     * @param mixed $value Valor a buscar
     * @return bool
     */
    public function exists(string $field, mixed $value): bool
    {
        try {
            if (is_string($value)) {
                // Case-insensitive para strings
                return $this->model->whereRaw(
                    "LOWER({$field}) = LOWER(?)",
                    [trim($value)]
                )->exists();
            }
            
            // Búsqueda exacta para otros tipos (int, bool, etc.)
            return $this->model->where($field, $value)->exists();
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while checking entity existence.");
        }
    }

    /**
     *
     * @param int $id
     * @param string[] $guardedRelations 
     * @throws NotFoundException
     * @throws BusinessConflictException
     * 
     */
    public function delete(int $id, array $guardedRelations = []): void
    {
        try {
            $entity = $this->findById($id);

            foreach ($guardedRelations as $relation) {
                if ($entity->{$relation}()->exists()) {
                    $entityName = class_basename($entity);
                    throw new BusinessConflictException("Cannot delete {$entityName}: it has associated records.");
                }
            }

            $entity->delete();
        } catch (BusinessConflictException $e) {
            throw $e;
        } catch (NotFoundException $e) {
            throw $e;
        } catch (QueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException("Unexpected error while deleting entity.");
        }
    }

    
}