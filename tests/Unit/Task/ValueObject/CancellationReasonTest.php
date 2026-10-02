<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\ValueObject;

use App\Task\ValueObject\CancellationReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CancellationReasonTest extends TestCase
{
    #[Test]
    public function itShouldTrimTheReason(): void
    {
        self::assertSame('No longer needed', new CancellationReason("  No longer needed\n")->value);
    }

    #[Test]
    public function itShouldCountCharactersNotBytes(): void
    {
        self::assertSame('éàü', new CancellationReason('éàü')->value);
        self::assertSame(500, mb_strlen(new CancellationReason(str_repeat('ü', 500))->value));
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['ab'])]
    #[TestWith(['  ab  '])]
    public function itShouldRefuseAReasonThatIsTooShort(string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CancellationReason($reason);
    }

    #[Test]
    public function itShouldRefuseAReasonThatIsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CancellationReason(str_repeat('a', 501));
    }
}
