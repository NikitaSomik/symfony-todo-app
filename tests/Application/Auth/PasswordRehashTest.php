<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\Entity\User;
use App\Auth\Repository\UserRepository;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PasswordRehashTest extends ApiTestCase
{
    #[Test]
    public function loginShouldRehashAPasswordHashedWithOutdatedSettings(): void
    {
        $outdated = password_hash('password', \PASSWORD_BCRYPT, ['cost' => 5]);
        $user = UserFactory::createOne(['email' => 'user@example.com', 'password' => $outdated]);

        $this->login();

        $stored = $this->storedHash($user);
        self::assertNotSame($outdated, $stored);
        self::assertTrue($this->hasher()->verify($stored, 'password'));
        self::assertFalse($this->hasher()->needsRehash($stored));
    }

    #[Test]
    public function loginShouldKeepACurrentPasswordHash(): void
    {
        $user = UserFactory::createOne(['email' => 'user@example.com']);
        $current = $user->getPassword();

        $this->login();

        self::assertSame($current, $this->storedHash($user));
    }

    #[Test]
    public function upgradePasswordShouldRefuseAUserOfAnotherClass(): void
    {
        $this->expectException(UnsupportedUserException::class);

        static::getContainer()->get(UserRepository::class)
            ->upgradePassword(new InMemoryUser('someone', 'hash'), 'new-hash');
    }

    private function login(): void
    {
        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    private function hasher(): PasswordHasherInterface
    {
        return static::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class);
    }

    private function storedHash(User $user): string
    {
        return (string) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT password FROM users WHERE id = ?', [$user->getId()]);
    }
}
