<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService implements AuditLogServiceInterface
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    /**
     * Registra una acción en el log de auditoría.
     *
     * El campo 'timestamp' no se incluye: PostgreSQL lo establece
     * con DEFAULT NOW() en el momento del INSERT.
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
    ): AuditLog {
        return $this->auditLogRepository->create([
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'action'         => $action,
            'user_id'        => $userId,
            'block_id'       => $blockId,
            'previous_value' => $previousValue,
            'new_value'      => $newValue,
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
        ]);
    }

    /**
     * Devuelve el historial de auditoría paginado para una entidad.
     */
    public function historyFor(
        string $entityType,
        string $entityId,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return $this->auditLogRepository->paginateByEntity($entityType, $entityId, $perPage);
    }

    /**
     * Verifica si un usuario tiene acceso a la auditoría de un documento.
     * 
     * @param string $documentId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessDocumentAudit(string $documentId, JwtUser $user): void
    {
        if (Document::query()->whereKey($documentId)->exists()) {
            return;
        }

        if (! $user->hasPermission('audit.read')) {
            abort(404);
        }

        Document::query()->withoutGlobalScopes(['user_access'])->findOrFail($documentId);
    }

    /**
     * Verifica si un usuario tiene acceso a la auditoría de una plantilla.
     * 
     * @param string $templateId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessTemplateAudit(string $templateId, JwtUser $user): void
    {
        if (Template::query()->whereKey($templateId)->exists()) {
            return;
        }

        if (! $user->hasPermission('audit.read')) {
            abort(404);
        }

        Template::query()->withoutGlobalScopes(['user_access'])->findOrFail($templateId);
    }

    /**
     * Verifica si un usuario tiene acceso a la auditoría de un comentario.
     * 
     * @param string $commentId
     * @param JwtUser $user
     * @return void
     */
    public function assertCanAccessCommentAudit(string $commentId, JwtUser $user): void
    {
        if (Comment::query()->whereKey($commentId)->exists()) {
            return;
        }

        if (! $user->hasPermission('audit.read')) {
            abort(404);
        }

        Comment::query()->withoutGlobalScopes(['user_access'])->findOrFail($commentId);
    }

    /**
     * Resuelve el process_id del documento para validar contexto opcional.
     */
    public function resolveDocumentProcessId(string $documentId): ?string
    {
        $processId = Document::query()
            ->withoutGlobalScopes(['user_access'])
            ->whereKey($documentId)
            ->value('process_id');

        return is_string($processId) ? $processId : null;
    }

    /**
     * Resuelve el process_id de la plantilla para validar contexto opcional.
     */
    public function resolveTemplateProcessId(string $templateId): ?string
    {
        $processId = Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->whereKey($templateId)
            ->value('process_id');

        return is_string($processId) ? $processId : null;
    }

    /**
     * Resuelve el process_id del comentario (vía documento o plantilla).
     */
    public function resolveCommentProcessId(string $commentId): ?string
    {
        $comment = Comment::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['document', 'template'])
            ->findOrFail($commentId);

        $processId = $comment->document?->process_id ?? $comment->template?->process_id;

        return is_string($processId) ? $processId : null;
    }
}
