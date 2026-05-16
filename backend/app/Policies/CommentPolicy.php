<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;

class CommentPolicy
{
    public function delete(JwtUser $user, Comment $comment): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $comment->author_id) {
            return true;
        }

        $commentable = $comment->commentable;
        if ($commentable === null) {
            return false;
        }

        if ($userId === (string) ($commentable->created_by ?? '')) {
            return true;
        }

        if ($commentable instanceof Template) {
            return $commentable->reviewers()
                ->where('user_id', $userId)
                ->exists();
        }

        if ($commentable instanceof Document) {
            return $commentable->reviews()
                ->where('reviewer_id', $userId)
                ->exists();
        }

        return false;
    }
}
