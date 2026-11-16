<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case GRACE = 'grace';
    case SUSPENDED = 'suspended';
    case CANCELED = 'canceled';

    /** A plan is had once it is paid, and kept while Stripe retries a failed charge. */
    public function grantsPlan(): bool
    {
        return match ($this) {
            self::ACTIVE, self::GRACE => true,
            self::PENDING, self::SUSPENDED, self::CANCELED => false,
        };
    }

    /** @return list<self> */
    public static function grantingPlan(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->grantsPlan()));
    }
}
