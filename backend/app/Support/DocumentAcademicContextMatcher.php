<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\JwtUser;

/**
 * Comprueba si el contexto académico del documento (snapshot cabezal) solapa
 * con el perfil del usuario (tipos/estudios/módulos, unión OR).
 *
 * Usado para acotar clonar y abrir nueva versión sin alterar la visibilidad de
 * catálogo ni el resto de mutaciones que siguen en {@see DocumentPolicy::viewScoped()}.
 */
final class DocumentAcademicContextMatcher
{
    public static function matches(JwtUser $user, Document $document): bool
    {
        if (self::matchesProfileLists($user, $document)) {
            return true;
        }

        $documentId = $document->getKey();
        if ($documentId === null || $documentId === '') {
            return false;
        }

        return Document::query()
            ->withoutGlobalScope('user_access')
            ->whereKey($documentId)
            ->where(function ($query) use ($user) {
                Document::applyAcademicOverlapForTableAlias(
                    $query,
                    (string) $user->getAuthIdentifier(),
                    'documents',
                );
            })
            ->exists();
    }

    private static function matchesProfileLists(JwtUser $user, Document $document): bool
    {
        $studyTypeId = self::normalizeId($document->study_type_id);
        $studyId = self::normalizeId($document->study_id);
        $moduleId = self::normalizeId($document->module_id);

        if ($studyTypeId !== null && in_array($studyTypeId, $user->studyTypeIds, true)) {
            return true;
        }

        if ($studyId !== null && in_array($studyId, $user->studyIds, true)) {
            return true;
        }

        if ($moduleId !== null && in_array($moduleId, $user->moduleIds, true)) {
            return true;
        }

        return false;
    }

    private static function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
