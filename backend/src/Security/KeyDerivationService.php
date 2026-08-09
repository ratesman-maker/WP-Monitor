<?php

declare(strict_types=1);

namespace WPMonitor\Security;

/**
 * Derives a 32-byte encryption key from a master password and per-user salt
 * using Argon2id via libsodium.
 *
 * Two opslimit/memlimit profiles are supported:
 *   - "interactive": faster, used for login (SODIUM_*_INTERACTIVE limits)
 *   - "sensitive":   slower, used for first setup (SODIUM_*_SENSITIVE limits)
 *
 * The derived key is used by {@see CryptoService} for AES-256-GCM encryption.
 */
final class KeyDerivationService
{
    public const PROFILE_INTERACTIVE = 'interactive';
    public const PROFILE_SENSITIVE = 'sensitive';

    public const SALT_BYTES = 16;
    public const KEY_BYTES = 32;

    public static function generateSalt(): string
    {
        return random_bytes(self::SALT_BYTES);
    }

    /**
     * @param self::PROFILE_INTERACTIVE|self::PROFILE_SENSITIVE $profile
     */
    public function deriveKey(string $password, string $salt, string $profile = self::PROFILE_INTERACTIVE): string
    {
        if (strlen($salt) !== self::SALT_BYTES) {
            throw new \InvalidArgumentException(
                'Salt must be ' . self::SALT_BYTES . ' bytes, got ' . strlen($salt)
            );
        }

        if ($password === '') {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        [$opsLimit, $memLimit] = $this->limitsForProfile($profile);

        $key = sodium_crypto_pwhash(
            self::KEY_BYTES,
            $password,
            $salt,
            $opsLimit,
            $memLimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        return $key;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function limitsForProfile(string $profile): array
    {
        if ($profile === self::PROFILE_INTERACTIVE) {
            return [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            ];
        }

        if ($profile === self::PROFILE_SENSITIVE) {
            return [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
            ];
        }

        throw new \InvalidArgumentException(
            "Unknown profile '$profile'. Use 'interactive' or 'sensitive'."
        );
    }
}
