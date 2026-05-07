<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\JwtUser;

/**
 * Autorización de documentos.
 *
 * El titular actual (owner tras delegación) puede enviar a revisión y editar.
 * Tener `documents.review` es suficiente para aprobar o rechazar, independientemente
 * de si el actor es también el creador o titular — igual que en plantillas.
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
     * Publicación explícita del documento: solo el titular.
     * La ausencia de revisiones pendientes se verifica en DocumentService::publishDocument,
     * no aquí; esta policy únicamente controla quién tiene permiso para invocar el endpoint.
     */
    public function publish(JwtUser $user, Document $document): bool
    {
        return $user->getAuthIdentifier() === $document->owner_id;
    }

    /**
     * Revisión / aprobación / rechazo del documento en flujo de revisión.
     * El servicio verifica adicionalmente que el actor sea el revisor asignado.
     */
    public function review(JwtUser $user, Document $document): bool
    {
        return $user->hasPermission('documents.review');
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
     * Delegación de titularidad a otro usuario: solo el titular actual.
     */
    public function delegate(JwtUser $user, Document $document): bool
    {
        return $user->getAuthIdentifier() === $document->owner_id;
    }

    /**
     * Envío a revisión (p. ej. transición draft → in_review): solo el titular actual.
     */
    public function submit(JwtUser $user, Document $document): bool
    {
        return $user->getAuthIdentifier() === $document->owner_id;
    }

    /**
     * Clonar documento: requiere poder crear documentos y mutar el origen (titular/creador/colaborador edit o permiso global).
     */
    public function clone(JwtUser $user, Document $document): bool
    {
        return $user->hasPermission('documents.create') && $this->update($user, $document);
    }

    /**
     * Publicado → borrador para preparar una nueva versión publicada del mismo expediente.
     *
     * Quién puede editar el borrador lo define {@see self::update}; aquí solo se exige documento `published`.
     * Quién puede cargar el modelo desde la API queda acotado por el scope del modelo (equivalente práctico a “ver”).
     */
    public function startRevision(JwtUser $user, Document $document): bool
    {
        if ($document->status !== 'published') {
            return false;
        }

        return $this->update($user, $document);
    }
}
