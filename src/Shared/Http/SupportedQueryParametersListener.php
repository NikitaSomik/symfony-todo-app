<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use App\Shared\Query\Sort;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * JSON:API requires a 400 for a query parameter the server does not know. The parameters an action
 * knows are the ones its #[MapQueryString] DTO can hold, so they are read from the DTO's constructor:
 * a scalar argument is a parameter, an object argument is a family such as "filter[...]".
 *
 * A "sort" field outside SortableQuery::sortFields() is rejected the same way.
 *
 * The serializer's "allow_extra_attributes" option cannot do this: it throws instead of collecting
 * the error, and the request ends with a 500.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final class SupportedQueryParametersListener
{
    /**
     * @var array<class-string, array<string, mixed>>
     */
    private array $shapes = [];

    public function __invoke(ControllerEvent $event): void
    {
        $dtoClass = $this->queryDtoClass($event->getControllerReflector());

        if (null === $dtoClass) {
            return;
        }

        $query = $event->getRequest()->query->all();

        $errors = [
            ...array_map(self::unsupportedParameter(...), $this->unknownParameters($query, $this->shape($dtoClass))),
            ...$this->unsupportedSorts($dtoClass, $query['sort'] ?? null),
        ];

        if ([] !== $errors) {
            throw JsonApiRequestException::of(Response::HTTP_BAD_REQUEST, ...$errors);
        }
    }

    /**
     * @param class-string $dtoClass
     *
     * @return list<JsonApiError>
     */
    private function unsupportedSorts(string $dtoClass, mixed $sort): array
    {
        // A "sort" that is not a string is a wrong type, left to validation.
        if (!is_a($dtoClass, SortableQuery::class, true) || !\is_string($sort)) {
            return [];
        }

        $errors = [];

        foreach (Sort::listFromQuery($sort) as $field) {
            if (!\in_array($field->field, $dtoClass::sortFields(), true)) {
                $errors[] = JsonApiError::forParameter(
                    (string) Response::HTTP_BAD_REQUEST,
                    sprintf('Sorting by "%s" is not supported.', $field->field),
                    'sort',
                );
            }
        }

        return $errors;
    }

    private static function unsupportedParameter(string $parameter): JsonApiError
    {
        return JsonApiError::forParameter(
            (string) Response::HTTP_BAD_REQUEST,
            sprintf('The "%s" query parameter is not supported.', $parameter),
            $parameter,
        );
    }

    /**
     * @return class-string|null
     */
    private function queryDtoClass(\ReflectionFunctionAbstract $controller): ?string
    {
        foreach ($controller->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ([] !== $parameter->getAttributes(MapQueryString::class) && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string $class */
                $class = $type->getName();

                return $class;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, mixed>    $shape
     *
     * @return list<string>
     */
    private function unknownParameters(array $query, array $shape, ?string $family = null): array
    {
        $unknown = [];

        foreach ($query as $key => $value) {
            $key = (string) $key;
            $name = null === $family ? $key : sprintf('%s[%s]', $family, $key);

            if (!\array_key_exists($key, $shape)) {
                $unknown[] = $name;

                continue;
            }

            if (!\is_array($shape[$key])) {
                // A value of the wrong type, such as "sort[]=x", is left to validation and answered with a 422.
                continue;
            }

            // A family used as a single value, such as "filter=abc", is a form the server does not support.
            if (!\is_array($value)) {
                $unknown[] = $name;

                continue;
            }

            /** @var array<string, mixed> $nestedShape */
            $nestedShape = $shape[$key];
            $unknown = [...$unknown, ...$this->unknownParameters($value, $nestedShape, $name)];
        }

        return $unknown;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed> parameter name => true for a value, or the nested shape for a family
     */
    private function shape(string $class): array
    {
        if (isset($this->shapes[$class])) {
            return $this->shapes[$class];
        }

        $shape = [];

        foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if (!self::isFamily($type)) {
                $shape[$parameter->getName()] = true;

                continue;
            }

            /** @var class-string $familyClass */
            $familyClass = $type->getName();
            $shape[$parameter->getName()] = $this->shape($familyClass);
        }

        return $this->shapes[$class] = $shape;
    }

    /**
     * @phpstan-assert-if-true \ReflectionNamedType $type
     */
    private static function isFamily(?\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $class = $type->getName();

        return !enum_exists($class) && !is_a($class, \DateTimeInterface::class, true);
    }
}
