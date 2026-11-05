<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Billing\Enum\Plan;
use App\Shared\Http\ActionableException;

final class CannotBuyPlanException extends \DomainException implements ActionableException
{
    private function __construct(
        string $message,
        private readonly Plan $plan,
    ) {
        parent::__construct($message);
    }

    public static function alreadySubscribed(Plan $plan): self
    {
        return new self('This workspace already has a paid plan. Change the plan instead of buying another.', $plan);
    }

    public function errorCode(): string
    {
        return 'already_subscribed';
    }

    public function errorTitle(): string
    {
        return 'Already subscribed';
    }

    public function errorMeta(): array
    {
        return ['plan' => $this->plan->value];
    }
}
