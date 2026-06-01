<?php

namespace App\Shared\Exceptions;
use Exception;

class BusinessConflictException extends Exception
{

    public function __construct($message = "Conflict in Business logic", $code = 409)
    {
        parent::__construct($message, $code);
    }
}