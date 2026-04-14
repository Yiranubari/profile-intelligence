<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateProfileException extends RuntimeException
{
    private array $profile;

    public function __construct(array $profile)
    {
        parent::__construct("Profile already exists");
        $this->profile = $profile;
    }

    public function getProfile(): array
    {
        return $this->profile;
    }
}
