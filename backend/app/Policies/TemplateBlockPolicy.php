<?php

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\TemplateBlock;

class TemplateBlockPolicy
{
    public function delete(JwtUser $user, TemplateBlock $block): bool
    {
        $template = $block->template;

        return $template !== null
            && (string) $user->getAuthIdentifier() === (string) $template->created_by;
    }
}
