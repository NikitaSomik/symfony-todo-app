<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\RefreshToken;

use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RandomRefreshTokenGeneratorTest extends TestCase
{
    #[Test]
    public function generateShouldReturn256BitHexToken(): void
    {
        $token = (new RandomRefreshTokenGenerator())->generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    #[Test]
    public function generateShouldReturnDifferentTokens(): void
    {
        $generator = new RandomRefreshTokenGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
