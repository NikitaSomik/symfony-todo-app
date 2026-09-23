<?php

declare(strict_types=1);

namespace App\Auth\Logging;

use App\Auth\Entity\User;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Stamps log records with the id of the authenticated user.
 *
 * The id, not the email: it identifies the user without putting personal data into the logs.
 */
#[AsMonologProcessor]
final readonly class UserIdProcessor
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof User && null !== $user->getId()) {
            $record->extra['user_id'] = $user->getId();
        }

        return $record;
    }
}
