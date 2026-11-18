<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use Symfony\Component\Uid\Uuid;

final class SecondSubscriptionException extends \DomainException
{
    public function __construct(
        public readonly Uuid $workspaceId,
        public readonly string $subscriptionId,
        public readonly string $currentSubscriptionId,
    ) {
        parent::__construct(sprintf(
            'The workspace %s already has the subscription %s, which is not canceled; %s is not added.',
            $workspaceId->toRfc4122(),
            $currentSubscriptionId,
            $subscriptionId,
        ));
    }
}
