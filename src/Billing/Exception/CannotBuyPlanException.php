<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Shared\Http\ClientFacingException;

final class CannotBuyPlanException extends \DomainException implements ClientFacingException
{
    public static function alreadySubscribed(): self
    {
        return new self('This workspace already has a paid plan. Change the plan instead of buying another.');
    }
}
