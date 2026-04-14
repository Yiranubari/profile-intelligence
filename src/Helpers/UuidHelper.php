<?php

namespace App\Helpers;

use Symfony\Component\Uid\Uuid;

class UuidHelper
{
    public static function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
