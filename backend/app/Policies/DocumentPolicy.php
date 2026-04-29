<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\JwtUser;

/**
 * Segregación de funciones (SoD) en documentos.
 *
 * Creador y titular actual (owner tras delegación) no pueden revisar ni aprobar
 * el mismo artefacto. Solo comparación de IDs en memoria — sin consultas extra.
 *
 * Envío a revisión ({@see self::submit}): solo el titular ({@see Document::$owner_id}).
 *
 * Mutaciones de persistencia: el creador o el titular pueden editar sin el permiso
 * global; un colaborador con share `edit` puede mutar contenido; el resto
 * requiere `documents.update`. `delete` sigue exigiendo `documents.delete`.
 *
 * Compartición ({@see self::share}): solo el titular actual gestiona filas en
 * `document_shares`.
 */
class DocumentPolicy
{
    /**
     * Ver documento: el alcance lo acota el global scope del modelo.
     * Los controladores deben resolver el modelo con {@see Document::query()} (no sin scope)
     * antes de delegar aquí.
     */
    public function view(JwtUser $user, Document $document): bool
    {
        return true;
    }

    /**
     * Editar metadatos, bloques u otras mutaciones de contenido del documento.
     */
    public function update(JwtUser $user, Document $document): bool
    {
        $id = $user->getAuthIdentifier();

        if ($id === $document->created_by || $id === $document->owner_id) {
            return true;
        }

        if ($this->hasEditShare($document, (string) $id)) {
            return true;
        }

        return $user->hasPermission('documents.update');
    }

    /**
     * Alta o modificación de compartidos del documento (POST shares).
     */
    public function share(JwtUser $user, Document $document): bool
    {
        return $user->getAuthIdentifier() === $document->owner_id;
    }

    private function hasEditShare(Document $document, string $userId): bool
    {
        if ($document->relationLoaded('shares')) {
            return $document->shares->contains(
                fn ($share) => (string) $share->user_id === $userId && $share->permission === 'edit',
            );
        }

        if ($document->getKey() === null) {
            return false;
        }

        return $document->shares()
            ->where('user_id', $userId)
            ->where('permission', 'edit')
            ->exists();
    }

    /**
     * Eliminar u operaciones de baja del documento.
     */
    public function delete(JwtUser $user, Document $document): bool
    {
        return $user->hasPermission('documents.delete');
    }

    /**
     * Revisión / aprobación / rechazo del documento en flujo de revisión.
     */
    public function review(JwtUser $user, Document $document): bool
    {
        return ! $this->violatesSegregation($user, $document);
    }

    /**
     * Ver/gestionar comentarios del documento.
     *
     * Requiere capacidad de edición o de revisión.
     */
    public function comment(JwtUser $user, Document $document): bool
    {
        return $this->update($user, $document) || $this->review($user, $document);
    }

    /**
     * Envío a revisión (p. ej. transición draft → in_review): solo el titular actual.
     */
    public function submit(JwtUser $user, Document $document): bool
    {
        return $user->getAuthIdentifier() === $document->owner_id;
    }

    /**
     * Verifica si el usuario viola la segregación de funciones.
     */
    private function violatesSegregation(JwtUser $user, Document $document): bool
    {
        $id = $user->getAuthIdentifier();

        return $id === $document->created_by || $id === $document->owner_id;
    }
}
