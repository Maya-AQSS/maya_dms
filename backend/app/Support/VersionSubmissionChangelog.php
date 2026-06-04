<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Normaliza y resuelve el changelog de envío a validación (versión de trabajo).
 */
final class VersionSubmissionChangelog
{
    public const int MAX_LENGTH = 5000;

    public static function normalize(?string $value): string
    {
        return trim((string) $value);
    }

    /**
     * @throws ValidationException
     */
    public static function requireNonEmpty(?string $explicit, ?string $fromHead): string
    {
        $resolved = self::normalize($explicit);
        if ($resolved === '') {
            $resolved = self::normalize($fromHead);
        }

        if ($resolved === '') {
            throw ValidationException::withMessages([
                'changelog' => ['El changelog es obligatorio al enviar a validación.'],
            ]);
        }

        if (strlen($resolved) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'changelog' => ['El changelog no puede superar '.self::MAX_LENGTH.' caracteres.'],
            ]);
        }

        return $resolved;
    }

    public static function fromHead(?string $headChangelog): ?string
    {
        $normalized = self::normalize($headChangelog);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Changelog de la versión en curso expuesto en API (borrador, rechazado o en revisión).
     */
    public static function forApiExposure(?string $entityStatus, ?string $headChangelog): ?string
    {
        if (! in_array($entityStatus, ['draft', 'rejected', 'in_review'], true)) {
            return null;
        }

        return self::fromHead($headChangelog);
    }
}
