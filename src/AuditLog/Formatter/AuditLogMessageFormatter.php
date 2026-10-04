<?php

declare(strict_types=1);

namespace App\AuditLog\Formatter;

final class AuditLogMessageFormatter
{
    public function created(string $entityLabel, string $displayName): string
    {
        return sprintf('Created %s "%s"', $entityLabel, $displayName);
    }

    /**
     * @param array<string, string> $fieldLabels
     */
    public function updated(string $entityLabel, string $displayName, string $field, array $fieldLabels = []): string
    {
        $label = $fieldLabels[$field] ?? $field;

        return sprintf('Updated %s %s for "%s"', $entityLabel, $label, $displayName);
    }

    public function deleted(string $entityLabel, string $displayName): string
    {
        return sprintf('Deleted %s "%s"', $entityLabel, $displayName);
    }
}
