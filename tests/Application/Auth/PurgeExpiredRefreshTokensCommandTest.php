<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Fixtures\Auth\RefreshTokenFactory;
use App\Fixtures\Auth\RefreshTokenFamilyFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeExpiredRefreshTokensCommandTest extends KernelTestCase
{
    #[Test]
    public function purgeShouldDeleteEndedSessionsWithTheirTokens(): void
    {
        RefreshTokenFactory::createOne(['family' => RefreshTokenFamilyFactory::new()->revoked()]);
        RefreshTokenFactory::createOne(['family' => RefreshTokenFamilyFactory::new()->expired()]);

        $this->purge();

        self::assertSame(0, RefreshTokenFamilyFactory::count());
        self::assertSame(0, RefreshTokenFactory::count());
    }

    #[Test]
    public function purgeShouldKeepALiveSession(): void
    {
        RefreshTokenFactory::createOne(['family' => RefreshTokenFamilyFactory::createOne()]);

        $this->purge();

        self::assertSame(1, RefreshTokenFamilyFactory::count());
        self::assertSame(1, RefreshTokenFactory::count());
    }

    #[Test]
    public function purgeShouldDeleteExpiredTokensOfALiveSession(): void
    {
        $family = RefreshTokenFamilyFactory::createOne();
        RefreshTokenFactory::new(['family' => $family])->expired()->create();
        RefreshTokenFactory::createOne(['family' => $family]);

        $this->purge();

        self::assertSame(1, RefreshTokenFactory::count());
    }

    private function purge(): void
    {
        $command = (new Application(self::bootKernel()))->find('app:auth:purge-expired-refresh-tokens');
        (new CommandTester($command))->execute([]);
    }
}
