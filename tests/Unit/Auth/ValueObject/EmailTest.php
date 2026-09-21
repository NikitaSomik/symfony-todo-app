<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\ValueObject;

use App\Auth\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    #[Test]
    #[DataProvider('addresses')]
    public function itShouldCanonicalizeTheAddress(string $input, string $expected): void
    {
        self::assertSame($expected, (new Email($input))->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'already canonical' => ['user@example.com', 'user@example.com'];
        yield 'upper case' => ['USER@EXAMPLE.COM', 'user@example.com'];
        yield 'mixed case' => ['User@Example.Com', 'user@example.com'];
        yield 'surrounding whitespace' => ["  user@example.com\n", 'user@example.com'];
        yield 'non-ascii local part' => ['MÜLLER@example.com', 'müller@example.com'];
    }

    #[Test]
    public function equalAddressesShouldProduceEqualValues(): void
    {
        self::assertSame((new Email('User@Example.com'))->value, (new Email('user@example.com'))->value);
    }
}
