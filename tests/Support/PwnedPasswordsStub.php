<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Answers the Pwned Passwords range API as haveibeenpwned.com does — the SHA-1 suffixes
 * under a five-character prefix — with one known leaked password. Any other request fails.
 */
final class PwnedPasswordsStub
{
    public const string LEAKED_PASSWORD = 'leaked-password-123';

    private bool $unreachable = false;

    public function makeUnreachable(): void
    {
        $this->unreachable = true;
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!str_starts_with($url, 'https://api.pwnedpasswords.com/range/')) {
            throw new \LogicException(sprintf('Unexpected outbound request in a test: %s %s', $method, $url));
        }

        if ($this->unreachable) {
            return new MockResponse(info: ['error' => 'Could not resolve host: api.pwnedpasswords.com']);
        }

        $leaked = strtoupper(sha1(self::LEAKED_PASSWORD));
        $prefix = substr($url, -5);
        $body = '0018A45C4D1DEF81644B54AB7F969B88D65:3'."\r\n";

        if (str_starts_with($leaked, $prefix)) {
            $body .= substr($leaked, 5).':42'."\r\n";
        }

        return new MockResponse($body);
    }
}
