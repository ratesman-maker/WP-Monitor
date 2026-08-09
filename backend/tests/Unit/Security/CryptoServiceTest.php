<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Security;

use WPMonitor\Security\CryptoService;
use WPMonitor\Security\DecryptionException;
use WPMonitor\Tests\TestCase;

final class CryptoServiceTest extends TestCase
{
    private function createKey(): string
    {
        return random_bytes(CryptoService::KEY_BYTES);
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $crypto = new CryptoService($this->createKey());
        $plaintext = 'my-secret-password-123';
        $encrypted = $crypto->encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertSame($plaintext, $crypto->decrypt($encrypted));
    }

    public function testEncryptProducesDifferentCiphertextsForSamePlaintext(): void
    {
        $crypto = new CryptoService($this->createKey());
        $plaintext = 'same-secret';

        $c1 = $crypto->encrypt($plaintext);
        $c2 = $crypto->encrypt($plaintext);

        $this->assertNotSame($c1, $c2);
        $this->assertSame($plaintext, $crypto->decrypt($c1));
        $this->assertSame($plaintext, $crypto->decrypt($c2));
    }

    public function testEncryptWithAadDecryptWithSameAadSucceeds(): void
    {
        $crypto = new CryptoService($this->createKey());
        $aad = CryptoService::buildAad(42, 7);

        $encrypted = $crypto->encrypt('secret', $aad);
        $this->assertSame('secret', $crypto->decrypt($encrypted, $aad));
    }

    public function testEncryptWithAadDecryptWithDifferentAadFails(): void
    {
        $crypto = new CryptoService($this->createKey());
        $encrypted = $crypto->encrypt('secret', CryptoService::buildAad(42, 7));

        $this->expectException(DecryptionException::class);
        $crypto->decrypt($encrypted, CryptoService::buildAad(99, 7));
    }

    public function testEncryptWithAadDecryptWithoutAadFails(): void
    {
        $crypto = new CryptoService($this->createKey());
        $encrypted = $crypto->encrypt('secret', 'aad-context');

        $this->expectException(DecryptionException::class);
        $crypto->decrypt($encrypted);
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $crypto1 = new CryptoService($this->createKey());
        $crypto2 = new CryptoService($this->createKey());

        $encrypted = $crypto1->encrypt('secret');

        $this->expectException(DecryptionException::class);
        $crypto2->decrypt($encrypted);
    }

    public function testDecryptTamperedCiphertextFails(): void
    {
        $crypto = new CryptoService($this->createKey());
        $encrypted = $crypto->encrypt('secret');
        $decoded = base64_decode($encrypted, true) ?: '';

        // Flip a bit in the ciphertext portion (after nonce)
        $tampered = $decoded;
        $tampered[12] = chr((ord($tampered[12]) ^ 0xFF));
        $tamperedEncoded = base64_encode($tampered) ?: '';

        $this->expectException(DecryptionException::class);
        $crypto->decrypt($tamperedEncoded);
    }

    public function testDecryptInvalidBase64Fails(): void
    {
        $crypto = new CryptoService($this->createKey());

        $this->expectException(DecryptionException::class);
        $crypto->decrypt('!!!not-base64!!!');
    }

    public function testDecryptTooShortBlobFails(): void
    {
        $crypto = new CryptoService($this->createKey());

        $this->expectException(DecryptionException::class);
        $crypto->decrypt(base64_encode(random_bytes(5)) ?: '');
    }

    public function testEncryptEmptyPlaintext(): void
    {
        $crypto = new CryptoService($this->createKey());
        $encrypted = $crypto->encrypt('');

        $this->assertSame('', $crypto->decrypt($encrypted));
    }

    public function testEncryptLargePlaintext(): void
    {
        $crypto = new CryptoService($this->createKey());
        $plaintext = str_repeat('x', 10000);
        $encrypted = $crypto->encrypt($plaintext);

        $this->assertSame($plaintext, $crypto->decrypt($encrypted));
    }

    public function testConstructorRejectsShortKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CryptoService(random_bytes(16));
    }

    public function testBuildAadProduces8Bytes(): void
    {
        $aad = CryptoService::buildAad(42, 7);
        $this->assertSame(8, strlen($aad));
    }

    public function testBuildAadIsDeterministic(): void
    {
        $this->assertSame(
            CryptoService::buildAad(42, 7),
            CryptoService::buildAad(42, 7)
        );
    }

    public function testBuildAadDiffersForDifferentSiteOrUser(): void
    {
        $this->assertNotSame(
            CryptoService::buildAad(42, 7),
            CryptoService::buildAad(43, 7)
        );
        $this->assertNotSame(
            CryptoService::buildAad(42, 7),
            CryptoService::buildAad(42, 8)
        );
    }
}
