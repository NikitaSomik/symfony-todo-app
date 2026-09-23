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
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '409', 'detail' => 'Email is already taken.']]],
            $this->normalize(new HttpException(409, $domain->getMessage(), $domain)),
        );
    }

    #[Test]
    public function unmarkedHttpExceptionShouldUseGenericDetail(): void
    {
        $internal = new \RuntimeException('SQLSTATE[08006] connection to database failed');

        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '409', 'detail' => 'Conflict']]],
            $this->normalize(new HttpException(409, $internal->getMessage(), $internal)),
        );
    }

    #[Test]
    public function unmappedExceptionShouldBecomeServerError(): void
    {
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '500', 'detail' => 'Server Error.']]],
            $this->normalize(new \RuntimeException('SQLSTATE[08006] connection to database failed')),
        );
    }

    #[Test]
    public function knownHttpExceptionShouldUseItsOwnText(): void
    {
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '404', 'detail' => 'Not Found.']]],
            $this->normalize(new NotFoundHttpException('No route found for "GET /api/v1/ghost"')),
        );
    }

    #[Test]
    public function queryViolationsShouldBecomeParameterNames(): void
    {
        $validation = new ValidationFailedException(new \stdClass(), new ConstraintViolationList([
            new ConstraintViolation('This value is not a valid date.', null, [], null, 'filter.due_to', 'not-a-date'),
            new ConstraintViolation('This value is not a valid choice.', null, [], null, 'sort', 'nope'),
        ]));

        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [
                ['status' => '422', 'detail' => 'This value is not a valid date.', 'source' => ['parameter' => 'filter[due_to]']],
                ['status' => '422', 'detail' => 'This value is not a valid choice.', 'source' => ['parameter' => 'sort']],
            ]],
            $this->normalize(new HttpException(422, 'Validation failed', $validation)),
        );
    }

    #[Test]
    public function violationWithoutAPathShouldCarryNoSource(): void
    {
        $validation = new ValidationFailedException(new \stdClass(), new ConstraintViolationList([
            new ConstraintViolation('The payload is invalid.', null, [], null, '', null),
        ]));

        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '422', 'detail' => 'The payload is invalid.']]],
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
