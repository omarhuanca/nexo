<?php

namespace App\Shared\Traits;

use Illuminate\Support\Str;

trait GettersAndSetters
{
    public function __call($method, $parameters)
    {
        if (str_starts_with($method, 'get')) {
            $property = Str::snake(substr($method, 3));

            if ($this->hasFillableAttribute($property) || array_key_exists($property, $this->attributes)) {
                return $this->getAttribute($property);
            }
        } 

        if (str_starts_with($method, 'set')) {
            $property = Str::snake(substr($method, 3));
            $this->setAttribute($property, $parameters[0] ?? null);
            return $this;
        }
        return parent::__call($method, $parameters);
    }

    public function hasFillableAttribute(string $key): bool
    {
        return in_array($key, $this->getFillable()) || $this->exists;
    }
}
