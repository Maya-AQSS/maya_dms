<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\JwtUser;

/**
 * Segregación de funciones (SoD) en documentos.
 *
 * Creador y titular actual (owner tras delegación) no pueden revisar ni aprobar
 * el mismo artefacto. Solo comparación de IDs en memoria — sin consultas extra.
 */
class DocumentPolicy
{
    /**
     * Ver documento: el alcance lo acota el global scope del modelo.
     */
    public function view(JwtUser $user, Document $document): bool
    {
        return true;
    }

    /**
     * Revisión / aprobación / rechazo del documento en flujo de revisión.
     */
    public function review(JwtUser $user, Document $document): bool
    {
        return ! $this->violatesSegregation($user, $document);
    }

    /**
     * Envío a revisión (p. ej. transición draft → in_review).
     */
    public function submit(JwtUser $user, Document $document): bool
    {
        return ! $this->violatesSegregation($user, $document);
    }

    private function violatesSegregation(JwtUser $user, Document $document): bool
    {
        $id = $user->getAuthIdentifier();

        return $id === $document->created_by || $id === $document->owner_id;
    }
}
