<?php

declare(strict_types=1);

namespace WPMonitor\Security;

use RuntimeException;

/**
 * Thrown when AES-256-GCM decryption fails — typically because of a wrong key,
 * tampered ciphertext, or mismatched Additional Authenticated Data (AAD).
 */
final class DecryptionException extends RuntimeException
{
    public static function create(): self
    {
        return new self(
            'Decryption failed — wrong key, tampered ciphertext, or AAD mismatch.'
        );
    }
}
