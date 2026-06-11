<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Repositories\Contracts\CommentReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CommentReadRepository implements CommentReadRepositoryInterface
{
    /**
     * Marca un comentario como leído para el usuario (idempotente).
     */
    public function markAsRead(string $commentId, string $userId): void
    {
        DB::table('comment_reads')->insertOrIgnore([
            'user_id' => $userId,
            'comment_id' => $commentId,
            'read_at' => now(),
        ]);
    }

    /**
     * Marca como leídos todos los comentarios activos de un bloque para el usuario.
     *
     * @return int Número de filas insertadas (comentarios recién marcados).
     */
    public function markBlockAsRead(
        string $userId,
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
    ): int {
        $commentIds = Comment::withTrashed()
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->where('commentable_version', $commentableVersion)
            ->where('blockable_type', $blockableType)
            ->where('blockable_id', $blockableId)
            ->where('comments.author_id', '!=', $userId)
            ->whereNotExists(function ($query) use ($userId): void {
                $query->select(DB::raw(1))
                    ->from('comment_reads')
                    ->whereColumn('comment_reads.comment_id', 'comments.id')
                    ->where('comment_reads.user_id', $userId);
            })
            ->pluck('id');

        if ($commentIds->isEmpty()) {
            return 0;
        }

        $readAt = now();
        $rows = $commentIds
            ->map(static fn ($id): array => [
                'user_id' => $userId,
                'comment_id' => (string) $id,
                'read_at' => $readAt,
            ])
            ->all();

        DB::table('comment_reads')->insertOrIgnore($rows);

        return count($rows);
    }
}
