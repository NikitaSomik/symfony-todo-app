<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class SigningKeyPairCheckTest extends TestCase
{
    private string $privateKeyBefore;

    protected function setUp(): void
    {
        $this->privateKeyBefore = $_SERVER['JWT_SECRET_KEY'];
    }

    protected function tearDown(): void
    {
        $_SERVER['JWT_SECRET_KEY'] = $_ENV['JWT_SECRET_KEY'] = $this->privateKeyBefore;
    }

    private function warmUpTheCacheOf(string $environment): CommandTester
    {
        $_SERVER['JWT_SECRET_KEY'] = $_ENV['JWT_SECRET_KEY'] = '/no/such/private.pem';
        $kernel = new Kernel($environment, debug: false);
        $kernel->boot();
        $command = new CommandTester((new Application($kernel))->find('cache:warmup'));

        try {
            $command->execute([]);
        } finally {
            $cacheDir = $kernel->getCacheDir();
            $kernel->shutdown();
            if ('prod' === $environment) {
                (new Filesystem())->remove($cacheDir);
            }
        }

        return $command;
    }

    #[Test]
    public function warmingTheProdCacheShouldFailWithoutThePrivateKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET_KEY');

        $this->warmUpTheCacheOf('prod');
    }

    #[Test]
    public function warmingTheTestCacheShouldNotCheckTheKeys(): void
    {
        $this->warmUpTheCacheOf('test')->assertCommandIsSuccessful();
    }
}
