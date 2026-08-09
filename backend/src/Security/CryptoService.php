<?php

declare(strict_types=1);

namespace WPMonitor\Security;

/**
 * AES-256-GCM authenticated encryption via libsodium.
 *
 * The encryption key (32 bytes) is derived from the master password by
 * {@see KeyDerivationService} and held by this instance for the lifetime of
 * a single request. Each encrypt() call generates a fresh random nonce.
 * The output blob is base64(nonce || ciphertext+tag).
 *
 * Additional Authenticated Data (AAD) binds a ciphertext to a context
 * (e.g. site_id + user_id) so it cannot be "moved" to another record.
 *
 * Note: PHP strings are value types (copy-on-write), so sodium_memzero on a
 * local variable does not wipe the caller's copy. Sensitive material (the
 * key) is therefore only kept in this instance for the request lifetime and
 * is never persisted in plaintext.
 */
final class CryptoService
{
    public const NONCE_BYTES = SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES; // 12
    public const KEY_BYTES = SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES; // 32

    public function __construct(
        private readonly string $key
    ) {
        if (strlen($key) !== self::KEY_BYTES) {
            throw new \InvalidArgumentException(
                'Encryption key must be ' . self::KEY_BYTES . ' bytes, got ' . strlen($key)
            );
        }
    }

    /**
     * Encrypt plaintext with optional AAD.
     * Returns base64(nonce || ciphertext+tag).
     */
    public function encrypt(string $plaintext, string $aad = ''): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);

        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
            $plaintext,
            $aad,
            $nonce,
            $this->key
        );

        return base64_encode($nonce . $ciphertext) ?: '';
    }

    /**
     * Decrypt a base64(nonce || ciphertext+tag) blob with optional AAD.
     *
     * @throws DecryptionException if decryption fails (wrong key, tampered, AAD mismatch)
     */
    public function decrypt(string $encrypted, string $aad = ''): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) < self::NONCE_BYTES) {
            throw DecryptionException::create();
        }

        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES);

        $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
            $ciphertext,
            $aad,
            $nonce,
            $this->key
        );

        if ($plaintext === false) {
            throw DecryptionException::create();
        }

        return $plaintext;
    }

    /**
     * Build an AAD blob binding a ciphertext to a site and user.
     * Format: 4-byte site_id (N) + 4-byte user_id (N), 8 bytes total.
     */
    public static function buildAad(int $siteId, int $userId): string
    {
        return pack('NN', $siteId, $userId);
    }
}
