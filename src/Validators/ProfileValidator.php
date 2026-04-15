<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use Respect\Validation\Validator as v;

class ProfileValidator
{
    public static function validate(mixed $body): void
    {
        if (!is_array($body) || !isset($body['name'])) {
            throw new ValidationException('Missing or empty name');
        }

        if (!v::stringType()->validate($body['name'])) {
            throw new ValidationException('Invalid type', 422);
        }

        if (trim($body['name']) === '') {
            throw new ValidationException('Missing or empty name');
        }
    }
}
