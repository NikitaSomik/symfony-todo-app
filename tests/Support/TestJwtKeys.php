<?php

declare(strict_types=1);

namespace App\Tests\Support;

/** The key pair the test environment signs access tokens with, generated on first use and never committed. */
final class TestJwtKeys
{
    public static function privateKeyPath(): string
    {
        return self::directory().'/private.pem';
    }

    public static function publicKeyPath(): string
    {
        return self::directory().'/public.pem';
    }

    public static function generateUnlessPresent(): void
    {
        if (is_file(self::privateKeyPath()) && is_file(self::publicKeyPath())) {
            return;
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if (false === $key || !openssl_pkey_export($key, $privateKey)) {
            throw new \RuntimeException('OpenSSL could not generate the test key pair.');
        }
        $details = openssl_pkey_get_details($key);
        if (false === $details) {
            throw new \RuntimeException('OpenSSL could not export the test public key.');
        }

        if (!is_dir(self::directory())) {
            mkdir(self::directory(), recursive: true);
        }
        // Written under temporary names and renamed, so that a parallel run never reads half a key.
        $suffix = bin2hex(random_bytes(4));
        file_put_contents(self::privateKeyPath().$suffix, $privateKey);
        file_put_contents(self::publicKeyPath().$suffix, $details['key']);
        rename(self::privateKeyPath().$suffix, self::privateKeyPath());
        rename(self::publicKeyPath().$suffix, self::publicKeyPath());
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 2).'/var/jwt-test';
    }
}
