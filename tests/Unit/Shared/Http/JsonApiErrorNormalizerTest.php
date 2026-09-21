<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\ClientFacingException;
use App\Shared\Http\JsonApiErrorNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class JsonApiErrorNormalizerTest extends TestCase
{
    #[Test]
    public function clientFacingExceptionShouldKeepItsMessage(): void
    {
        $domain = new class('Email is already taken.') extends \DomainException implements ClientFacingException {};

        self::assertSame(
            ['errors' => [['status' => '409', 'message' => 'Email is already taken.']]],
            $this->normalize(new HttpException(409, $domain->getMessage(), $domain)),
        );
    }

    #[Test]
    public function unmarkedHttpExceptionShouldUseGenericMessage(): void
    {
        $internal = new \RuntimeException('SQLSTATE[08006] connection to database failed');

        self::assertSame(
            ['errors' => [['status' => '409', 'message' => 'Conflict']]],
            $this->normalize(new HttpException(409, $internal->getMessage(), $internal)),
        );
    }

    #[Test]
    public function unmappedExceptionShouldBecomeServerError(): void
    {
        self::assertSame(
            ['errors' => [['status' => '500', 'message' => 'Server Error.']]],
            $this->normalize(new \RuntimeException('SQLSTATE[08006] connection to database failed')),
        );
    }

    #[Test]
    public function knownHttpExceptionShouldUseItsOwnText(): void
    {
        self::assertSame(
            ['errors' => [['status' => '404', 'message' => 'Not Found.']]],
            $this->normalize(new NotFoundHttpException('No route found for "GET /api/v1/ghost"')),
        );
    }

    #[Test]
    public function violationsShouldBecomeOneErrorPerFieldWithTheResponseStatus(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value is not a valid email address.', null, [], null, 'email', 'not-an-email'),
            new ConstraintViolation('This value is too short.', null, [], null, 'password', '123'),
        ]);

        $validation = new ValidationFailedException(new \stdClass(), $violations);

        self::assertSame(
            ['errors' => [
                ['status' => '422', 'message' => 'This value is not a valid email address.', 'field' => 'email'],
                ['status' => '422', 'message' => 'This value is too short.', 'field' => 'password'],
            ]],
            $this->normalize(new HttpException(422, 'Validation failed', $validation)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(\Throwable $throwable): array
    {
        return (new JsonApiErrorNormalizer())->normalize(
            FlattenException::createFromThrowable($throwable),
            'json',
            ['exception' => $throwable],
        );
    }
}
