<?php

declare(strict_types=1);

namespace App\Billing\Exception;

use App\Shared\Http\ActionableException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(503, headers: ['Retry-After' => '30'])]
final class PaymentProviderUnavailableException extends \RuntimeException implements ActionableException
{
    public static function because(\Throwable $previous): self
    {
        return new self('The payment provider did not answer. Try again shortly.', previous: $previous);
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
