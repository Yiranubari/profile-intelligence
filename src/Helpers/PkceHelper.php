<?php

namespace App\Helpers;

class PkceHelper
{
    public static function verifyChallenge(string $verifier, string $challenge): bool
    {
        $hash = hash('sha256', $verifier, true);
        $computed = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
}
