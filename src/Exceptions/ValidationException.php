<?php

namespace App\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    public function __construct(string $message, private int $statusCode = 400)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
