<?php
declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentBlock;
use App\Models\JwtUser;

class DocumentBlockPolicy
{
    public function delete(JwtUser $user, DocumentBlock $block): bool
    {
        $document = $block->document;
        if ($document === null) {
            return false;
        }

        $id = (string) $user->getAuthIdentifier();

        // Mirrors DocumentPolicy::update — creator or current owner may delete optional blocks.
        // block_state and draft-status validation are delegated to DocumentBlockService.
        return $id === (string) $document->created_by
            || $id === (string) $document->owner_id;
    }
}
