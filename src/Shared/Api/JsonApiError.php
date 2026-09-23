<?php

declare(strict_types=1);

namespace App\Shared\Api;

final readonly class JsonApiError
{
    /**
     * @param array{pointer: string}|array{parameter: string}|array{header: string}|null $source
     */
    private function __construct(
        public string $status,
        public string $detail,
        public ?array $source = null,
    ) {
    }

    public static function of(string $status, string $detail): self
    {
        return new self($status, $detail);
    }

    /**
     * For a value inside the request document, addressed by an RFC 6901 JSON Pointer.
     */
    public static function forPointer(string $status, string $detail, string $pointer): self
    {
        return new self($status, $detail, ['pointer' => $pointer]);
    }

    /**
     * For a value that arrived as a URI query parameter, named as the client sent it.
     */
    public static function forParameter(string $status, string $detail, string $parameter): self
    {
        return new self($status, $detail, ['parameter' => $parameter]);
    }

    /**
     * For a request header, such as a Content-Type the server does not accept.
     */
    public static function forHeader(string $status, string $detail, string $header): self
    {
        return new self($status, $detail, ['header' => $header]);
    }

    /**
     * @return array{status: string, detail: string, source?: array{pointer: string}|array{parameter: string}|array{header: string}}
     */
    public function toArray(): array
    {
        $error = ['status' => $this->status, 'detail' => $this->detail];

        if (null !== $this->source) {
            $error['source'] = $this->source;
        }

        return $error;
    }
}
