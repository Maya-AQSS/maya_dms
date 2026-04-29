<?php

namespace App\Services\Contracts;

use App\Models\AuditLog;
use App\Models\JwtUser;
use Illuminate\Pagination\LengthAwarePaginator;

interface AuditLogServiceInterface
{
    /**
     * Registra un nuevo evento de auditoría.
     * 
     * @param string $entityType
     * @param string $entityId
     * @param string $action
     * @param string $userId
     * @param string|null $blockId
     * @param array|null $previousValue 
     * @param array|null $newValue
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return AuditLog
     */
    public function record(
        string $entityType,
        string $entityId,
        string $action,
        string $userId,
        ?string $blockId = null,
        ?array $previousValue = null,
        ?array $newValue = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog;

    /**
     * Obtiene el historial de auditoría para una entidad.
     * 
     * @param string $entityType
     * @param string $entityId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function historyFor(
        string $entityType,
        string $entityId,
        int $perPage = 25,
    ): LengthAwarePaginator;

    /**
     * Verifica si un usuario tiene acceso a la auditoría de un documento.
     * 
     * @param string $documentId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessDocumentAudit(string $documentId, JwtUser $user): void;

    /**
     * Verifica si un usuario tiene acceso a la auditoría de una plantilla.
     * 
     * @param string $templateId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessTemplateAudit(string $templateId, JwtUser $user): void;

    /**
     * Verifica si un usuario tiene acceso a la auditoría de un comentario.
     * 
     * @param string $commentId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessCommentAudit(string $commentId, JwtUser $user): void;

    /**
     * Resuelve el process_id del documento para validar contexto opcional.
     */
    public function resolveDocumentProcessId(string $documentId): ?string;

    /**
     * Resuelve el process_id de la plantilla para validar contexto opcional.
     */
    public function resolveTemplateProcessId(string $templateId): ?string;

    /**
     * Resuelve el process_id del comentario (vía documento o plantilla).
     */
    public function resolveCommentProcessId(string $commentId): ?string;
}
