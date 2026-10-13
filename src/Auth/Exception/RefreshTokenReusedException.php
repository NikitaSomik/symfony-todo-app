<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use Symfony\Component\Uid\Uuid;

/** The client gets the same answer as for any invalid token; the family is already revoked. */
final class RefreshTokenReusedException extends InvalidRefreshTokenException
{
    public function __construct(
        public readonly int $userId,
        public readonly Uuid $familyId,
    ) {
        parent::__construct();
    }
}
