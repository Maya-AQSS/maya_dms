<?php

namespace App\Services;

use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;

class CommentService implements CommentServiceInterface
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
    ) {}

    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment
    {
        return $this->commentRepository->findOrFail($id);
    }

    public function listForTemplate(string $templateId): \Illuminate\Support\Collection
    {
        return Comment::where('template_id', $templateId)
            ->with('author:id,name')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function createForTemplate(string $templateId, string $authorId, array $data): Comment
    {
        return Comment::create([
            'template_id' => $templateId,
            'template_block_id' => $data['template_block_id'] ?? null,
            'author_id' => $authorId,
            'body' => $data['body'],
            'type' => $data['type'] ?? 'general',
        ]);
    }

    public function resolve(string $id, string $userId): Comment
    {
        $comment = $this->findOrFail($id);
        $comment->update([
            'resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
        return $comment;
    }
}
