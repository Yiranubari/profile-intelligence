<?php

namespace App\Exceptions;

use RuntimeException;

class ExternalApiException extends RuntimeException
{
    public function __construct(string $apiName)
    {
        parent::__construct("{$apiName} returned an invalid response");
    }
}
