<?php

namespace App\Policies;

use App\Models\DocumentBlock;
use App\Models\JwtUser;

class DocumentBlockPolicy
{
    public function delete(JwtUser $user, DocumentBlock $block): bool
    {
        $templateBlock = $block->templateBlock;
        if ($templateBlock === null || $templateBlock->block_state !== 'optional') {
            return false;
        }

        $document = $block->document;

        return $document !== null
            && (string) $user->getAuthIdentifier() === (string) $document->created_by;
    }
}
