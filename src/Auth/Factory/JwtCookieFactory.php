<?php

declare(strict_types=1);

namespace App\Auth\Factory;

use App\Auth\Entity\RefreshToken;
use Symfony\Component\HttpFoundation\Cookie;

final class JwtCookieFactory
{
    public const string JWT_COOKIE = 'access_token';
    public const string REFRESH_COOKIE = 'refresh_token';

    public function __construct(
        private readonly int $jwtTtl,
        private readonly bool $secure,
    ) {
    }

    public function createJwtCookie(string $jwt): Cookie
    {
        return Cookie::create(self::JWT_COOKIE)
            ->withValue($jwt)
            ->withExpires(time() + $this->jwtTtl)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withSecure($this->secure);
    }

    public function createRefreshCookie(RefreshToken $refreshToken): Cookie
    {
        return Cookie::create(self::REFRESH_COOKIE)
            ->withValue($refreshToken->getToken())
            ->withExpires($refreshToken->getExpiresAt())
            ->withPath('/api/v1/auth')
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withSecure($this->secure);
    }

    public function clearJwtCookie(): Cookie
    {
        return Cookie::create(self::JWT_COOKIE)
            ->withExpires(new \DateTimeImmutable('1970-01-01'))
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withSecure($this->secure);
    }

    public function clearRefreshCookie(): Cookie
    {
        return Cookie::create(self::REFRESH_COOKIE)
            ->withExpires(new \DateTimeImmutable('1970-01-01'))
            ->withPath('/api/v1/auth')
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withSecure($this->secure);
    }
}
