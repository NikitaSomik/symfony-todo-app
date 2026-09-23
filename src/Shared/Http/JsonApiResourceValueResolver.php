<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Api\JsonApiError;
use App\Shared\Api\JsonApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves #[MapJsonApiResource] arguments from a JSON:API request document.
 *
 * Like Symfony's #[MapRequestPayload], the body is read only after the controller's #[IsGranted]
 * checks have run: resolve() hands out a placeholder and onControllerArguments() replaces it. A caller
 * who may not touch a resource learns nothing from how its request body would have been judged.
 */
#[AsTargetedValueResolver]
final readonly class JsonApiResourceValueResolver implements ValueResolverInterface, EventSubscriberInterface
{
    public function __construct(
        private ResourceDocumentMapper $mapper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Same priority as Symfony's RequestPayloadValueResolver: after #[IsGranted] (-10000).
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerArguments', -10100]];
    }

    /**
     * @return iterable<UnresolvedResource>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(MapJsonApiResource::class)[0] ?? null;
        $type = $argument->getType();

        if (null === $attribute || null === $type || !class_exists($type)) {
            return [];
        }

        return [new UnresolvedResource($attribute, $type)];
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        $arguments = $event->getArguments();

        foreach ($arguments as $index => $argument) {
            if (!$argument instanceof UnresolvedResource) {
                continue;
            }

            $document = $this->read($event->getRequest(), $argument->attribute);

            $arguments[$index] = ResourceDocument::class === $argument->class
                ? $document
                : $this->mapper->map($document, $argument->class);
        }

        $event->setArguments($arguments);
    }

    private function read(Request $request, MapJsonApiResource $attribute): ResourceDocument
    {
        $this->assertMediaType($request);

        $document = $this->decode($request->getContent());

        if (!$document instanceof \stdClass) {
            throw self::rejected(Response::HTTP_BAD_REQUEST, 'The request body must be a JSON:API document.', '');
        }

        $data = $document->data ?? null;

        if (!$data instanceof \stdClass) {
            throw self::rejected(Response::HTTP_BAD_REQUEST, 'The document must hold a single resource object in "data".', '/data');
        }

        return new ResourceDocument(
            $this->type($data, $attribute->type),
            $this->id($data, $attribute, $request),
            $this->attributes($data),
        );
    }

    /**
     * JSON:API documents travel as application/vnd.api+json. No extension or profile is supported,
     * so a media type parameter is refused as well.
     */
    private function assertMediaType(Request $request): void
    {
        if (JsonApiResponse::MEDIA_TYPE === strtolower(trim((string) $request->headers->get('Content-Type')))) {
            return;
        }

        throw JsonApiRequestException::of(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, JsonApiError::forHeader((string) Response::HTTP_UNSUPPORTED_MEDIA_TYPE, sprintf('The request body must be sent as %s, without media type parameters.', JsonApiResponse::MEDIA_TYPE), 'Content-Type'));
    }

    private function decode(string $content): mixed
    {
        try {
            return json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw JsonApiRequestException::of(Response::HTTP_BAD_REQUEST, JsonApiError::of((string) Response::HTTP_BAD_REQUEST, 'The request body is not valid JSON.'));
        }
    }

    private function type(\stdClass $data, string $expected): string
    {
        $type = $data->type ?? null;

        if (!\is_string($type)) {
            throw self::rejected(Response::HTTP_BAD_REQUEST, 'The resource object must have a "type".', '/data/type');
        }

        if ($type !== $expected) {
            throw self::rejected(Response::HTTP_CONFLICT, sprintf('This endpoint accepts "%s" resources, not "%s".', $expected, $type), '/data/type');
        }

        return $type;
    }

    private function id(\stdClass $data, MapJsonApiResource $attribute, Request $request): ?string
    {
        $id = $data->id ?? null;

        if (null === $attribute->idFromRoute) {
            if (property_exists($data, 'id')) {
                throw self::rejected(Response::HTTP_FORBIDDEN, 'Client-generated ids are not supported.', '/data/id');
            }

            return null;
        }

        if (!\is_string($id)) {
            throw self::rejected(Response::HTTP_BAD_REQUEST, 'The resource object must have an "id".', '/data/id');
        }

        if ($id !== (string) $request->attributes->get($attribute->idFromRoute)) {
            throw self::rejected(Response::HTTP_CONFLICT, 'The resource object "id" does not match the resource being updated.', '/data/id');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(\stdClass $data): array
    {
        if (!property_exists($data, 'attributes')) {
            return [];
        }

        if (!$data->attributes instanceof \stdClass) {
            throw self::rejected(Response::HTTP_BAD_REQUEST, 'The resource object "attributes" must be an object.', '/data/attributes');
        }

        /** @var array<string, mixed> $attributes */
        $attributes = json_decode((string) json_encode($data->attributes), true);

        return $attributes;
    }

    private static function rejected(int $status, string $detail, string $pointer): JsonApiRequestException
    {
        return JsonApiRequestException::of($status, JsonApiError::forPointer((string) $status, $detail, $pointer));
    }
}
