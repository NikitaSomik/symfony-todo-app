<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class ErrorFormatNegotiationTest extends ApiTestCase
{
    /**
     * A client that sends no usable Accept header — curl sends "*\/*" by default — would otherwise
     * be answered with an HTML error page, because the renderer falls back to the "html" format.
     */
    #[Test]
    #[TestWith(['*/*'])]
    #[TestWith(['text/html,application/xhtml+xml'])]
    public function errorShouldBeJsonWhateverTheClientAccepts(string $accept): void
    {
        $response = $this->accepting($accept)->get('/api/v1/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.api+json');
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '404', 'detail' => 'Not Found.']]],
            $this->json($response),
        );
    }
}
