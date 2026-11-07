<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Shared\Http\ActionableException;

final class AlreadySubscribedException extends \DomainException implements ActionableException
{
    public function __construct()
    {
        parent::__construct('This workspace already has a subscription. Change the plan instead of buying another.');
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
