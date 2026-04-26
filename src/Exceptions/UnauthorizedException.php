<?php

namespace App\Exceptions;

use RuntimeException;

class UnauthorizedException extends RuntimeException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message);
    }
}
