<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\ValueObject;

use App\Task\ValueObject\BlockReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class BlockReasonTest extends TestCase
{
    #[Test]
    public function itShouldTrimTheReason(): void
    {
        self::assertSame('Waiting for access', new BlockReason("  Waiting for access\n")->value);
    }

    #[Test]
    public function itShouldCountCharactersNotBytes(): void
    {
        self::assertSame('éàü', new BlockReason('éàü')->value);
        self::assertSame(500, mb_strlen(new BlockReason(str_repeat('ü', 500))->value));
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['ab'])]
    #[TestWith(['  ab  '])]
    public function itShouldRefuseAReasonThatIsTooShort(string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BlockReason($reason);
    }

    #[Test]
    public function itShouldRefuseAReasonThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BlockReason(str_repeat('a', 501));
    }
}
