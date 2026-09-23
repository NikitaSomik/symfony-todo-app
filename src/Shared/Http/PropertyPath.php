<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Converts a validator property path ("page.number", "items[0].name") into the forms JSON:API reports.
 */
final class PropertyPath
{
    /**
     * An RFC 6901 JSON Pointer: "page.number" becomes "/page/number", "items[0].name" becomes "/items/0/name".
     */
    public static function toJsonPointer(string $propertyPath): string
    {
        return implode('', array_map(
            static fn (string $token): string => '/'.strtr($token, ['~' => '~0', '/' => '~1']),
            self::tokens($propertyPath),
        ));
    }

    /**
     * A query parameter name as the client sent it: "filter.due_to" becomes "filter[due_to]".
     */
    public static function toParameterName(string $propertyPath): string
    {
        $tokens = self::tokens($propertyPath);
        $name = (string) array_shift($tokens);

        foreach ($tokens as $token) {
            $name .= '['.$token.']';
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $propertyPath): array
    {
        return preg_split('/[.\[\]]+/', $propertyPath, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
