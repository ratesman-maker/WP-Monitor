<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Security;

use WPMonitor\Security\KeyDerivationService;
use WPMonitor\Tests\TestCase;

final class KeyDerivationServiceTest extends TestCase
{
    public function testDeriveKeyReturns32Bytes(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();
        $key = $service->deriveKey('test-password-123', $salt);

        $this->assertSame(KeyDerivationService::KEY_BYTES, strlen($key));
    }

    public function testDeriveKeyIsDeterministicWithSamePasswordAndSalt(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();

        $key1 = $service->deriveKey('same-password', $salt);
        $key2 = $service->deriveKey('same-password', $salt);

        $this->assertSame($key1, $key2);
    }

    public function testDeriveKeyDiffersForDifferentSalt(): void
    {
        $service = new KeyDerivationService();
        $salt1 = KeyDerivationService::generateSalt();
        $salt2 = KeyDerivationService::generateSalt();

        $key1 = $service->deriveKey('same-password', $salt1);
        $key2 = $service->deriveKey('same-password', $salt2);

        $this->assertNotSame($key1, $key2);
    }

    public function testDeriveKeyDiffersForDifferentPassword(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();

        $key1 = $service->deriveKey('password-one', $salt);
        $key2 = $service->deriveKey('password-two', $salt);

        $this->assertNotSame($key1, $key2);
    }

    public function testGenerateSaltReturns16Bytes(): void
    {
        $salt = KeyDerivationService::generateSalt();
        $this->assertSame(KeyDerivationService::SALT_BYTES, strlen($salt));
    }

    public function testGenerateSaltProducesRandomSalts(): void
    {
        $salt1 = KeyDerivationService::generateSalt();
        $salt2 = KeyDerivationService::generateSalt();
        $this->assertNotSame($salt1, $salt2);
    }

    public function testDeriveKeyRejectsEmptyPassword(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();

        $this->expectException(\InvalidArgumentException::class);
        $service->deriveKey('', $salt);
    }

    public function testDeriveKeyRejectsWrongSaltLength(): void
    {
        $service = new KeyDerivationService();

        $this->expectException(\InvalidArgumentException::class);
        $service->deriveKey('password', random_bytes(8));
    }

    public function testSensitiveProfileProducesDifferentKeyThanInteractive(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();

        $interactiveKey = $service->deriveKey('test-password', $salt, KeyDerivationService::PROFILE_INTERACTIVE);
        $sensitiveKey = $service->deriveKey('test-password', $salt, KeyDerivationService::PROFILE_SENSITIVE);

        $this->assertNotSame($interactiveKey, $sensitiveKey);
        $this->assertSame(KeyDerivationService::KEY_BYTES, strlen($sensitiveKey));
    }

    public function testDeriveKeyRejectsUnknownProfile(): void
    {
        $service = new KeyDerivationService();
        $salt = KeyDerivationService::generateSalt();

        $this->expectException(\InvalidArgumentException::class);
        $service->deriveKey('password', $salt, 'unknown');
    }
}
