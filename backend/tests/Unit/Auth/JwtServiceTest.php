<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Auth;

use WPMonitor\Auth\JwtException;
use WPMonitor\Auth\JwtService;
use WPMonitor\Tests\TestCase;

final class JwtServiceTest extends TestCase
{
    private const APP_KEY = 'base64:' . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='; // 32+ bytes
    private const ISSUER = 'wp-monitor-test';
    private const ACCESS_TTL = 900;
    private const REFRESH_TTL = 604800;

    private function createService(): JwtService
    {
        return new JwtService(
            self::APP_KEY,
            self::ISSUER,
            self::ACCESS_TTL,
            self::REFRESH_TTL
        );
    }

    public function testIssueAndVerifyAccessToken(): void
    {
        $service = $this->createService();
        $issued = $service->issueAccessToken(42, 'admin');

        $this->assertNotEmpty($issued['token']);
        $this->assertNotEmpty($issued['jti']);
        $this->assertGreaterThan(time(), $issued['expiresAt']);

        $claims = $service->verify($issued['token'], JwtService::TYPE_ACCESS);

        $this->assertSame(42, $claims['sub']);
        $this->assertSame('admin', $claims['role']);
        $this->assertSame($issued['jti'], $claims['jti']);
        $this->assertSame(self::ISSUER, $claims['iss']);
        $this->assertSame(JwtService::TYPE_ACCESS, $claims['type']);
    }

    public function testIssueAndVerifyRefreshToken(): void
    {
        $service = $this->createService();
        $issued = $service->issueRefreshToken(42, 'admin');

        $claims = $service->verify($issued['token'], JwtService::TYPE_REFRESH);

        $this->assertSame(JwtService::TYPE_REFRESH, $claims['type']);
        $this->assertGreaterThan(time() + self::ACCESS_TTL, $claims['exp']);
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        // TTL of 0 means token expires immediately
        $service = new JwtService(self::APP_KEY, self::ISSUER, 0, 0);
        $issued = $service->issueAccessToken(1, 'viewer');

        // Sleep 1 second to ensure exp < now
        sleep(1);

        $this->expectException(JwtException::class);
        $service->verify($issued['token']);
    }

    public function testVerifyRejectsWrongSignature(): void
    {
        $service1 = new JwtService(
            'base64:' . base64_encode(random_bytes(32)),
            self::ISSUER,
            self::ACCESS_TTL,
            self::REFRESH_TTL
        );
        $service2 = new JwtService(
            'base64:' . base64_encode(random_bytes(32)),
            self::ISSUER,
            self::ACCESS_TTL,
            self::REFRESH_TTL
        );

        $token = $service1->issueAccessToken(1, 'admin')['token'];

        $this->expectException(JwtException::class);
        $service2->verify($token);
    }

    public function testVerifyRejectsWrongIssuer(): void
    {
        $service1 = new JwtService(self::APP_KEY, 'issuer-one', self::ACCESS_TTL, self::REFRESH_TTL);
        $service2 = new JwtService(self::APP_KEY, 'issuer-two', self::ACCESS_TTL, self::REFRESH_TTL);

        $token = $service1->issueAccessToken(1, 'admin')['token'];

        $this->expectException(JwtException::class);
        $service2->verify($token);
    }

    public function testVerifyRejectsWrongTokenType(): void
    {
        $service = $this->createService();
        $refreshToken = $service->issueRefreshToken(1, 'admin')['token'];

        $this->expectException(JwtException::class);
        $service->verify($refreshToken, JwtService::TYPE_ACCESS);
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $service = $this->createService();

        $this->expectException(JwtException::class);
        $service->verify('not.a.valid.jwt.token');
    }

    public function testVerifyRejectsTokenWithOnlyTwoParts(): void
    {
        $service = $this->createService();

        $this->expectException(JwtException::class);
        $service->verify('two.parts');
    }

    public function testExtractJtiReturnsJtiFromValidToken(): void
    {
        $service = $this->createService();
        $issued = $service->issueAccessToken(1, 'admin');

        $this->assertSame($issued['jti'], $service->extractJti($issued['token']));
    }

    public function testExtractJtiReturnsNullForMalformedToken(): void
    {
        $service = $this->createService();

        $this->assertNull($service->extractJti('malformed'));
    }

    public function testEachTokenHasUniqueJti(): void
    {
        $service = $this->createService();
        $t1 = $service->issueAccessToken(1, 'admin');
        $t2 = $service->issueAccessToken(1, 'admin');

        $this->assertNotSame($t1['jti'], $t2['jti']);
    }

    public function testConstructorRejectsShortKey(): void
    {
        $this->expectException(\RuntimeException::class);
        new JwtService('base64:' . base64_encode(random_bytes(16)), self::ISSUER, 900, 604800);
    }
}
