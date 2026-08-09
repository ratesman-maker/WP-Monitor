<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * JWT issue/verify service using HS256 (HMAC-SHA256).
 *
 * The signing key is derived from APP_KEY (base64-decoded). Tokens carry:
 *   - sub: user ID
 *   - role: user role
 *   - jti: unique token ID (UUID) — used for revocation via session lookup
 *   - iss: issuer (from config)
 *   - iat: issued at (unix timestamp)
 *   - exp: expiration (unix timestamp)
 *
 * Refresh tokens use the same format but with a longer exp and a "type":"refresh" claim.
 */
final class JwtService
{
    public const TYPE_ACCESS = 'access';
    public const TYPE_REFRESH = 'refresh';

    private const ALG = 'HS256';

    private string $signingKey;

    public function __construct(
        string $appKey,
        private readonly string $issuer,
        private readonly int $accessTtl,
        private readonly int $refreshTtl
    ) {
        $key = $this->decodeAppKey($appKey);
        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY must decode to at least 32 bytes.');
        }
        $this->signingKey = $key;
    }

    /**
     * Issue an access token for a user.
     *
     * @return array{token: string, jti: string, expiresAt: int}
     */
    public function issueAccessToken(int $userId, string $role): array
    {
        $jti = Uuid::uuid4()->toString();
        $now = time();
        $expiresAt = $now + $this->accessTtl;

        $payload = [
            'sub' => $userId,
            'role' => $role,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $expiresAt,
            'type' => self::TYPE_ACCESS,
        ];

        return [
            'token' => $this->encode($payload),
            'jti' => $jti,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Issue a refresh token for a user.
     *
     * @return array{token: string, jti: string, expiresAt: int}
     */
    public function issueRefreshToken(int $userId, string $role): array
    {
        $jti = Uuid::uuid4()->toString();
        $now = time();
        $expiresAt = $now + $this->refreshTtl;

        $payload = [
            'sub' => $userId,
            'role' => $role,
            'jti' => $jti,
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $expiresAt,
            'type' => self::TYPE_REFRESH,
        ];

        return [
            'token' => $this->encode($payload),
            'jti' => $jti,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Verify a token and return its claims.
     *
     * @return array<string,mixed>
     *
     * @throws JwtException if the token is invalid, expired, or has a wrong signature/issuer/type
     */
    public function verify(string $token, ?string $expectedType = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw JwtException::invalid('Malformed token.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            throw JwtException::invalid('Invalid signature.');
        }

        $header = $this->base64UrlDecode($headerEncoded);
        $headerData = json_decode($header, true);
        if (!is_array($headerData) || ($headerData['alg'] ?? '') !== self::ALG) {
            throw JwtException::invalid('Unsupported algorithm.');
        }

        $payload = $this->base64UrlDecode($payloadEncoded);
        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            throw JwtException::invalid('Malformed payload.');
        }

        $now = time();

        if (($claims['exp'] ?? 0) < $now) {
            throw JwtException::expired();
        }

        if (($claims['iss'] ?? '') !== $this->issuer) {
            throw JwtException::invalid('Wrong issuer.');
        }

        if ($expectedType !== null && ($claims['type'] ?? '') !== $expectedType) {
            throw JwtException::invalid('Wrong token type.');
        }

        return $claims;
    }

    /**
     * Extract the JTI from a token without full verification.
     * Used for session lookup during revocation checks.
     */
    public function extractJti(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = $this->base64UrlDecode($parts[1]);
        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return null;
        }

        $jti = $claims['jti'] ?? null;

        return is_string($jti) ? $jti : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function encode(array $payload): string
    {
        $header = ['alg' => self::ALG, 'typ' => 'JWT'];
        $headerEncoded = $this->base64UrlEncode(json_encode($header) ?: '');
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload) ?: '');
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->signingKey, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw JwtException::invalid('Malformed base64.');
        }

        return $decoded;
    }

    private function decodeAppKey(string $appKey): string
    {
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('APP_KEY has invalid base64 encoding.');
            }

            return $decoded;
        }

        return $appKey;
    }
}
