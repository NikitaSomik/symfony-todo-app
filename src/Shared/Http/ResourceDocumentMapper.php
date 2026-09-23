<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Maps a resource object's attributes into a DTO and validates it, reporting each problem
 * with a pointer into the request document ("/data/attributes/title").
 */
final readonly class ResourceDocumentMapper
{
    private const string ATTRIBUTES_POINTER = '/data/attributes';

    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $current the resource's current attributes; for a PATCH, JSON:API reads
     *                                      an attribute the client left out as if it were sent with this value
     *
     * @return T
     */
    public function map(ResourceDocument $document, string $class, array $current = []): object
    {
        $attributes = array_replace($current, $document->attributes);

        try {
            $dto = $this->denormalizer->denormalize($attributes, $class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $exception) {
            throw $this->invalid($this->typeViolations($exception));
        }

        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            throw $this->invalid($violations);
        }

        return $dto;
    }

    private function typeViolations(PartialDenormalizationException $exception): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach ($exception->getNotNormalizableValueErrors() as $error) {
            $expectedTypes = $error->getExpectedTypes() ?? [];
            $message = [] === $expectedTypes
                ? 'This value was of an unexpected type.'
                : sprintf('This value should be of type %s.', implode('|', $expectedTypes));

            $violations->add(new ConstraintViolation($message, null, [], null, (string) $error->getPath(), null));
        }

        return $violations;
    }

    private function invalid(ConstraintViolationListInterface $violations): JsonApiRequestException
    {
        $status = (string) Response::HTTP_UNPROCESSABLE_ENTITY;
        $errors = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $errors[] = JsonApiError::forPointer(
                $status,
                (string) $violation->getMessage(),
                self::ATTRIBUTES_POINTER.PropertyPath::toJsonPointer($violation->getPropertyPath()),
            );
        }

        return JsonApiRequestException::of(Response::HTTP_UNPROCESSABLE_ENTITY, ...$errors);
    }
}
