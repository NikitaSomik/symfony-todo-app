<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workspace\Security;

use App\Workspace\Security\MeetServiceTokenHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class MeetServiceTokenHandlerTest extends TestCase
{
    #[Test]
    public function theServiceTokenShouldNameMeet(): void
    {
        $badge = (new MeetServiceTokenHandler('the-service-token'))->getUserBadgeFrom('the-service-token');

        self::assertSame('meet', $badge->getUserIdentifier());
    }

    #[Test]
    #[TestWith(['the-service-token', 'another-token'])]
    #[TestWith(['the-service-token', ''])]
    #[TestWith(['', ''])]
    #[TestWith(['', 'anything'])]
    public function anythingElseShouldBeRefusedAndSoShouldEverythingWhileNoTokenIsSet(string $serviceToken, string $presented): void
    {
        $this->expectException(BadCredentialsException::class);

        (new MeetServiceTokenHandler($serviceToken))->getUserBadgeFrom($presented);
    }
}
