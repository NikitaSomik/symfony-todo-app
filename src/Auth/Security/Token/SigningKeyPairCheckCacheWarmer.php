<?php

declare(strict_types=1);

namespace App\Auth\Security\Token;

use Lexik\Bundle\JWTAuthenticationBundle\Services\KeyLoader\KeyLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Lexik reads the keys only when the first token is issued or verified, so a deploy without
 * them would boot and then fail every login. Warming the cache is part of the deploy.
 *
 * Optional, so that it runs on cache:warmup and not whenever a kernel boots on an empty
 * cache: lint:container --env=prod in CI has no keys and needs none.
 */
#[When(env: 'prod')]
final readonly class SigningKeyPairCheckCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.key_loader')]
        private KeyLoaderInterface $keys,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $privateKey = openssl_pkey_get_private((string) $this->keys->getSigningKey(), (string) $this->keys->getPassphrase());
        if (false === $privateKey) {
            throw new \RuntimeException('Access tokens cannot be signed: JWT_SECRET_KEY is not a private key or a readable file that holds one.');
        }

        $privateKeyDetails = openssl_pkey_get_details($privateKey);
        if (false === $privateKeyDetails || OPENSSL_KEYTYPE_RSA !== $privateKeyDetails['type']) {
            throw new \RuntimeException('Access tokens cannot be signed: JWT_SECRET_KEY is not an RSA key, which RS256 needs.');
        }

        $publicKey = openssl_pkey_get_public((string) $this->keys->getPublicKey());
        if (false === $publicKey) {
            throw new \RuntimeException('Access tokens cannot be verified: JWT_PUBLIC_KEY is not a public key or a readable file that holds one.');
        }

        $message = random_bytes(32);
        $signature = '';
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!is_string($signature) || 1 !== openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('JWT_SECRET_KEY and JWT_PUBLIC_KEY do not belong together: a token signed with one would be refused by the other.');
        }

        return [];
    }
}
