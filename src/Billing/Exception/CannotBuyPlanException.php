<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Shared\Http\ActionableException;

final class CannotBuyPlanException extends \DomainException implements ActionableException
{
    public static function alreadySubscribed(): self
    {
        return new self('This workspace already has a subscription. Change the plan instead of buying another.');
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
        return [];
    }
}
