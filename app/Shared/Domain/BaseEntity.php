<?php

namespace App\Shared\Domain;

use Illuminate\Database\Eloquent\Model;

abstract class BaseEntity extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    public function updateDetails(array $data): void
    {
        $this->fill($data);
    }
}