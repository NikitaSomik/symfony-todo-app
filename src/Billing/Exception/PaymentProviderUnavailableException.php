<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Shared\Http\ActionableException;
use App\Shared\Http\RetryableException;

final class PaymentProviderUnavailableException extends \RuntimeException implements ActionableException, RetryableException
{
    public static function because(\Throwable $previous): self
    {
        return new self('The payment provider did not answer. Try again in a minute.', previous: $previous);
    }

    public function retryAfterSeconds(): int
    {
        return 30;
    }

    public function errorCode(): string
    {
        return 'payment_provider_unavailable';
    }

    public function errorTitle(): string
    {
        return 'Payment provider unavailable';
    }

    public function errorMeta(): array
    {
        return [];
    }
}
