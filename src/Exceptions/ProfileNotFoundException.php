<?php

namespace App\Exceptions;

use RuntimeException;

class ProfileNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Profile with id {$id} not found");
    }
}
