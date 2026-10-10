<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Listens on the event bus in tests and keeps what was published, in order.
 */
final class PublishedEvents
{
    /** @var list<object> */
    private array $events = [];

    public function __invoke(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
    }
}
