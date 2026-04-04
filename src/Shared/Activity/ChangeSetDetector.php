<?php

declare(strict_types=1);

namespace App\Shared\Activity;

final class ChangeSetDetector
{
    /**
     * @param array<string, scalar|null> $before
     * @param array<string, scalar|null> $after
     *
     * @return array<string, array{old: scalar|null, new: scalar|null}>
     */
    public function detect(array $before, array $after): array
    {
        $changes = [];

        foreach ($before as $field => $oldValue) {
            $newValue = $after[$field] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
