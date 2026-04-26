<?php

namespace App\Exceptions;

use RuntimeException;

class OAuthException extends RuntimeException
{
    public function __construct(string $message = 'OAuth flow failed')
    {
        parent::__construct($message);
    }
}
