<?php

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Validators\ProfileValidator;
use PHPUnit\Framework\TestCase;

class ProfileValidatorTest extends TestCase
{
    public function testValidNamePassesValidation(): void
    {
        $this->expectNotToPerformAssertions();
        ProfileValidator::validate(['name' => 'emma']);
    }

    public function testMissingNameThrows400(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing or empty name');
        ProfileValidator::validate([]);
    }

    public function testEmptyNameThrows400(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing or empty name');
        ProfileValidator::validate(['name' => '']);
    }

    public function testWhitespaceOnlyNameThrows400(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing or empty name');
        ProfileValidator::validate(['name' => '   ']);
    }

    public function testNullBodyThrows400(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing or empty name');
        ProfileValidator::validate(null);
    }

    public function testIntegerNameThrows422(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid type');
        ProfileValidator::validate(['name' => 123]);
    }

    public function testBooleanNameThrows422(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid type');
        ProfileValidator::validate(['name' => true]);
    }

    public function testArrayNameThrows422(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid type');
        ProfileValidator::validate(['name' => ['emma']]);
    }

    public function test400StatusCode(): void
    {
        try {
            ProfileValidator::validate([]);
        } catch (ValidationException $e) {
            $this->assertEquals(400, $e->getStatusCode());
        }
    }

    public function test422StatusCode(): void
    {
        try {
            ProfileValidator::validate(['name' => 123]);
        } catch (ValidationException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }
    }
}
