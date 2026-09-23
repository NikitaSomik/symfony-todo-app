<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Http\RequestId;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stamps every log record written while handling a request with that request's id.
 */
#[AsMonologProcessor]
final readonly class RequestIdProcessor
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getMainRequest();

        if (null !== $request) {
            $record->extra['request_id'] = RequestId::of($request);
        }

        return $record;
    }
}
