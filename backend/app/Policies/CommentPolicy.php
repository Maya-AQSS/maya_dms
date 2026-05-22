<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;

/**
 * Comentarios en bloques de plantilla o documento (y versiones vía commentable_version).
 *
 * - `comment-block.create`: crear; actor creador del recurso o revisor en `in_review`.
 * - `comment-block.delete`: eliminar; mismo contexto (propio comentario u otros si eres creador/revisor).
 * - `update`: solo el autor del comentario (sin slug; la API aún no expone PUT).
 */
class CommentPolicy
{
    public function update(JwtUser $user, Comment $comment): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $comment->author_id;
    }

    public function delete(JwtUser $user, Comment $comment): bool
    {
        $userId = (string) $user->getAuthIdentifier();
        $isAuthor = $userId === (string) $comment->author_id;

        if (! $isAuthor && ! $user->hasPermission('comment-block.delete')) {
            return false;
        }

        return $this->mayParticipateInComments($user, $comment);
    }

    /**
     * Creador del recurso o revisor asignado en revisión (plantilla/documento).
     */
    public function mayParticipateInComments(JwtUser $user, Comment $comment): bool
    {
        $commentable = $comment->commentable;
        if ($commentable === null) {
            return false;
        }

        if ($commentable instanceof Template) {
            return $this->mayParticipateOnTemplate($user, $commentable);
        }

        if ($commentable instanceof Document) {
            return $this->mayParticipateOnDocument($user, $commentable);
        }

        return false;
    }

    public function mayParticipateOnTemplate(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $template->created_by) {
            return true;
        }

        if ($template->status !== 'in_review') {
            return false;
        }

        return (new TemplatePolicy)->review($user, $template)
            || $template->reviewers()
                ->where('user_id', $userId)
                ->exists();
    }

    public function mayParticipateOnDocument(JwtUser $user, Document $document): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $document->created_by || $userId === (string) $document->owner_id) {
            return true;
        }

        if ($document->status !== 'in_review') {
            return false;
        }

        return (new DocumentPolicy)->review($user, $document)
            || $document->reviews()
                ->where('reviewer_id', $userId)
                ->exists();
    }
}
