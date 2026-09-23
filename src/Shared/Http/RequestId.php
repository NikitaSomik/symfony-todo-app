<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The id that ties a request's response to its log records.
 *
 * It is created on first use, from a log record or from the response, so it does not depend
 * on the order of the kernel listeners.
 */
final class RequestId
{
    public const string HEADER = 'X-Request-Id';

    private const string ATTRIBUTE = '_request_id';

    /**
     * Letters, digits, dots, underscores and hyphens: enough for UUIDs and the ids proxies generate,
     * and nothing that could forge a log line.
     */
    private const string FORMAT = '/^[A-Za-z0-9._-]{1,128}$/';

    public static function of(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        if (!\is_string($id)) {
            $id = self::incoming($request) ?? Uuid::v7()->toRfc4122();
            $request->attributes->set(self::ATTRIBUTE, $id);
        }

        return $id;
    }

    /**
     * An id sent by a trusted proxy keeps one id across the proxy's logs and ours.
     * From anyone else the header is ignored: a client must not choose what our logs say.
     */
    private static function incoming(Request $request): ?string
    {
        $id = $request->headers->get(self::HEADER);

        if (!$request->isFromTrustedProxy() || null === $id || 1 !== preg_match(self::FORMAT, $id)) {
            return null;
        }

        return $id;
    }
}
