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

        // Paridad con BlockPolicy::deleteForDocument / DocumentPolicy::update.
        return (new BlockPolicy)->deleteForDocument($user, $document);
    }
}
