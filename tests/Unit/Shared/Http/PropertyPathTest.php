<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\PropertyPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PropertyPathTest extends TestCase
{
    #[Test]
    #[TestWith(['title', '/title'])]
    #[TestWith(['page.number', '/page/number'])]
    #[TestWith(['items[0].name', '/items/0/name'])]
    #[TestWith(['a/b', '/a~1b'])]
    #[TestWith(['a~b', '/a~0b'])]
    #[TestWith(['', ''])]
    public function toJsonPointerShouldFollowRfc6901(string $propertyPath, string $pointer): void
    {
        self::assertSame($pointer, PropertyPath::toJsonPointer($propertyPath));
    }

    #[Test]
    #[TestWith(['sort', 'sort'])]
    #[TestWith(['filter.due_to', 'filter[due_to]'])]
    #[TestWith(['page.number', 'page[number]'])]
    public function toParameterNameShouldUseTheBracketFormClientsSend(string $propertyPath, string $parameter): void
    {
        self::assertSame($parameter, PropertyPath::toParameterName($propertyPath));
    }
}
