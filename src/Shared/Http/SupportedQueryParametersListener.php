<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
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

        $unknown = $this->unknownParameters($event->getRequest()->query->all(), $this->shape($dtoClass));

        if ([] === $unknown) {
            return;
        }

        throw JsonApiRequestException::of(Response::HTTP_BAD_REQUEST, ...array_map(self::unsupported(...), $unknown));
    }

    private static function unsupported(string $parameter): JsonApiError
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
