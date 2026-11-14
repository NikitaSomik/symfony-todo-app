<?php

declare(strict_types=1);

namespace App\Workspace\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class MeetServiceTokenHandler implements AccessTokenHandlerInterface
{
    /** The user of this name in the `services` provider of security.yaml. */
    private const string MEET = 'meet';

    public function __construct(
        #[Autowire(env: 'MEET_SERVICE_TOKEN')]
        #[\SensitiveParameter]
        private string $serviceToken,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        // An unset token must open nothing: two empty strings are equal too.
        if ('' === $this->serviceToken || !hash_equals($this->serviceToken, $accessToken)) {
            throw new BadCredentialsException();
        }

        return new UserBadge(self::MEET);
    }
}
