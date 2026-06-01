<?php

namespace App\Shared\Exceptions;

use Exception;

class DomainValidationException extends Exception
{
    private array $errors;

    public function __construct(string $message = "Invalid domain data", array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}