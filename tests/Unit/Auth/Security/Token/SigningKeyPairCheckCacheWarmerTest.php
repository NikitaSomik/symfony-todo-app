<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Security\Token;

use App\Auth\Security\Token\SigningKeyPairCheckCacheWarmer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\KeyLoader\RawKeyLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SigningKeyPairCheckCacheWarmerTest extends TestCase
{
    /** @return array{private: string, public: string} */
    private static function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertNotFalse($details);

        return ['private' => $privateKey, 'public' => $details['key']];
    }

    /** @return list<string> */
    private static function warmUp(?string $privateKey, ?string $publicKey): array
    {
        return (new SigningKeyPairCheckCacheWarmer(new RawKeyLoader($privateKey, $publicKey, '')))->warmUp(cacheDir: sys_get_temp_dir());
    }

    #[Test]
    public function warmUpShouldAcceptAPrivateKeyAndItsPublicKey(): void
    {
        $pair = self::keyPair();

        self::assertSame([], self::warmUp($pair['private'], $pair['public']));
    }

    #[Test]
    public function warmUpShouldRefuseAMissingPrivateKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET_KEY');

        self::warmUp('', self::keyPair()['public']);
    }

    #[Test]
    public function warmUpShouldRefuseAPrivateKeyThatIsNotAKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET_KEY');

        self::warmUp('a-former-hs256-secret', self::keyPair()['public']);
    }

    #[Test]
    public function warmUpShouldRefuseAMissingPublicKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_PUBLIC_KEY');

        self::warmUp(self::keyPair()['private'], '');
    }

    #[Test]
    public function warmUpShouldRefuseAPublicKeyOfAnotherPrivateKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('do not belong together');

        self::warmUp(self::keyPair()['private'], self::keyPair()['public']);
    }

    #[Test]
    public function warmUpShouldRefuseAPairThatIsNotRsa(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertNotFalse($details);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not an RSA key');

        self::warmUp($privateKey, $details['key']);
    }
}
