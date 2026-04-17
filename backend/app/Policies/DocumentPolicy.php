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
 * Mutaciones de persistencia (`update`, `delete`) exigen los códigos JWT
 * `documents.update` y `documents.delete` respectivamente.
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
        return $user->hasPermission('documents.update');
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
