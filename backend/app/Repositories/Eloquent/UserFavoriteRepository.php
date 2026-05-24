<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserFavoriteRepository implements UserFavoriteRepositoryInterface
{
    /**
     * Lista de IDs de plantillas favoritas del usuario.
     *
     * @return list<string>
     */
    public function listTemplateIdsForUser(string $userId): array
    {
        return DB::table('user_favorite_templates')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'desc')
            ->pluck('template_version_id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Lista de IDs de documentos favoritos del usuario.
     *
     * @return list<string>
     */
    public function listDocumentIdsForUser(string $userId): array
    {
        return DB::table('user_favorite_documents')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'desc')
            ->pluck('document_id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Añade una plantilla favorita al usuario.
     */
    public function addTemplateFavorite(string $userId, string $templateVersionId): void
    {
        $now = now();
        DB::table('user_favorite_templates')->insertOrIgnore([
            'user_id' => $userId,
            'template_version_id' => $templateVersionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Elimina una versión de plantilla favorita del usuario.
     */
    public function removeTemplateFavorite(string $userId, string $templateVersionId): void
    {
        DB::table('user_favorite_templates')
            ->where('user_id', '=', $userId)
            ->where('template_version_id', '=', $templateVersionId)
            ->delete();
    }

    /**
     * Añade un documento favorito al usuario.
     */
    public function addDocumentFavorite(string $userId, string $documentId): void
    {
        $now = now();
        DB::table('user_favorite_documents')->insertOrIgnore([
            'user_id' => $userId,
            'document_id' => $documentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Elimina un documento favorito del usuario.
     */
    public function removeDocumentFavorite(string $userId, string $documentId): void
    {
        DB::table('user_favorite_documents')
            ->where('user_id', '=', $userId)
            ->where('document_id', '=', $documentId)
            ->delete();
    }
}
