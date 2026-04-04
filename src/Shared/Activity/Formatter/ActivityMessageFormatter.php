<?php

declare(strict_types=1);

namespace App\Shared\Activity\Formatter;

final class ActivityMessageFormatter
{
    public function created(string $entityLabel, string $displayName): string
    {
        return sprintf('Created %s "%s"', $entityLabel, $displayName);
    }

    /**
     * @param array<string, array{old: scalar|null, new: scalar|null}> $changes
     * @param array<string, string>                                    $fieldLabels
     */
    public function updated(string $entityLabel, string $displayName, array $changes, array $fieldLabels = []): string
    {
        if (1 !== count($changes)) {
            return sprintf('Updated %s "%s"', $entityLabel, $displayName);
        }

        $field = array_key_first($changes);
        $label = $fieldLabels[$field] ?? $field;

        return sprintf('Updated %s %s for "%s"', $entityLabel, $label, $displayName);
    }

    public function deleted(string $entityLabel, string $displayName): string
    {
        return sprintf('Deleted %s "%s"', $entityLabel, $displayName);
    }
}
