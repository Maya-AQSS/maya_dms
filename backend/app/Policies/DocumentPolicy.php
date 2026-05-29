<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use Illuminate\Support\Facades\DB;

/**
 * Autorización de documentos.
 *
 * El titular actual (owner tras delegación) puede enviar a revisión y editar.
 * `document.review` exige además fila en `document_reviews` (revisores definidos en la
 * plantilla y materializados al enviar a revisión; no hay asignación manual en documento).
 *
 * LISTADO Y DETALLE (catálogo):
 * - `document.index`: listar documentos; el global scope `user_access` acota filas visibles.
 * - `document.show`: ver detalle; creador y titular no requieren este slug; revisores
 *   asignados en `in_review` tampoco.
 *
 * MUTACIONES (catálogo):
 * - `document.create`: crear programaciones (y anclar a plantilla visible).
 * - `document.update`: editar ajenos con slug; creador, titular o share `edit` sin slug.
 * - `document.delete`: borrar ajenos con slug; creador/titular el suyo sin slug.
 *   `update`/`delete` con slug exigen además {@see self::view()} (contexto académico).
 *
 * Compartición ({@see self::share}): solo el titular actual gestiona filas en
 * `document_shares`.
 *
 * VERSIONADO Y HISTORIAL (catálogo):
 * - `document.version`: abrir ciclo de nueva versión sobre publicada (no titular).
 * - `document.clone`: clonar publicada (no titular); además `document.update` o ser titular.
 * - `document.history.view`: listar/ver snapshots publicados (`GET …/versions`).
 */
class DocumentPolicy
{
    /**
     * Listar documentos: requiere `document.index`; el scope `user_access` acota filas visibles.
     */
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('document.index');
    }

    /**
     * Ver detalle de un documento.
     *
     * Creador, titular y revisor asignado en `in_review` no requieren `document.show`.
     * El resto necesita `document.show` y visibilidad vía scope académico (o snapshot publicado).
     * `document.delete` no amplía la vista: solo autoriza borrar en {@see self::delete}.
     *
     * Los controladores que resuelven sin scope deben delegar aquí tras comprobar snapshot.
     */
    public function view(JwtUser $user, Document $document): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ((string) $document->created_by === $userId || (string) $document->owner_id === $userId) {
            return true;
        }

        $documentId = $document->getKey();
        if ($documentId === null || $documentId === '') {
            return true;
        }

        if ($document->status === 'in_review'
            && DB::table('document_reviews')
                ->where('document_id', $documentId)
                ->where('reviewer_id', $userId)
                ->exists()) {
            return true;
        }

        if (! $user->hasPermission('document.show')) {
            return false;
        }

        // Catálogo visible (scope user_access): compartidos en contexto académico; excluye
        // documentos personales ajenos (visibilidad heredada de plantilla personal).
        if (Document::query()->whereKey($documentId)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Crear documentos / programaciones.
     */
    public function create(JwtUser $user): bool
    {
        return $user->hasPermission('document.create');
    }

    /**
     * Editar metadatos, bloques u otras mutaciones de contenido del documento.
     */
    public function update(JwtUser $user, Document $document): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $document->created_by || $userId === (string) $document->owner_id) {
            return true;
        }

        if ($this->hasEditShare($document, $userId)) {
            return true;
        }

        if (! $user->hasPermission('document.update')) {
            return false;
        }

        return $this->view($user, $document);
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
     *
     * El titular o creador puede borrar el suyo sin `document.delete`.
     * Con slug global hace falta además poder ver el documento (scope académico).
     */
    public function delete(JwtUser $user, Document $document): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $document->owner_id || $userId === (string) $document->created_by) {
            return true;
        }

        if (! $user->hasPermission('document.delete')) {
            return false;
        }

        return $this->view($user, $document);
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
     *
     * Requiere `document.review` y estar en `document_reviews` (pool de la plantilla).
     * El servicio verifica además etapa, estado pendiente y modo secuencial/paralelo.
     */
    public function review(JwtUser $user, Document $document): bool
    {
        if (! $user->hasPermission('document.review')) {
            return false;
        }

        return $document->reviews()
            ->where('reviewer_id', (string) $user->getAuthIdentifier())
            ->exists();
    }

    /**
     * Crear/listar comentarios en bloques de documento (`comment-block.create` + titular/creador o revisor).
     */
    public function comment(JwtUser $user, Document $document): bool
    {
        if (! $user->hasPermission('comment-block.create')) {
            return false;
        }

        return (new CommentPolicy)->mayParticipateOnDocument($user, $document);
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
     * Clonar documento publicado en un expediente nuevo.
     *
     * Requiere ver el origen, `document.create` y (titular o `document.clone`).
     * Quien no es titular necesita además `document.update` (misma línea que editar ajenos).
     */
    public function clone(JwtUser $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        $documentId = (string) $document->getKey();
        if ($documentId === '') {
            return false;
        }

        $hasPublishedSnapshot = EntityVersion::query()
            ->where('versionable_type', Document::class)
            ->where('versionable_id', $documentId)
            ->where('version_number', '>', 0)
            ->where('status', 'published')
            ->exists();

        if (! $hasPublishedSnapshot) {
            return false;
        }

        if (! $user->hasPermission('document.create')) {
            return false;
        }

        if ($this->isTitular($user, $document)) {
            return true;
        }

        if (! $user->hasPermission('document.clone')) {
            return false;
        }

        return $user->hasPermission('document.update');
    }

    /**
     * Descarta la versión de trabajo (draft/in_review) y restaura la última publicación.
     * Solo el creador, el titular o quien tenga `document.update` puede descartar.
     */
    public function discard(JwtUser $user, Document $document): bool
    {
        if (! in_array($document->status, ['draft', 'in_review'], true)) {
            return false;
        }

        $id = $user->getAuthIdentifier();

        return $id === $document->created_by || $id === $document->owner_id;
    }

    /**
     * Ver historial de versiones publicadas ({@see DocumentVersionController}).
     */
    public function viewHistory(JwtUser $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if ($this->isTitular($user, $document)) {
            return true;
        }

        return $user->hasPermission('document.history.view');
    }

    /**
     * Publicado → borrador para preparar una nueva versión publicada del mismo expediente.
     *
     * Titular o permiso `document.version`, siempre que pueda ver el documento.
     */
    public function startRevision(JwtUser $user, Document $document): bool
    {
        if ($document->status !== 'published') {
            return false;
        }

        if (! $this->view($user, $document)) {
            return false;
        }

        return $this->isTitular($user, $document) || $user->hasPermission('document.version');
    }

    private function isTitular(JwtUser $user, Document $document): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        return $userId === (string) $document->created_by || $userId === (string) $document->owner_id;
    }
}
