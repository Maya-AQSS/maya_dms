<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\TemplateBlock;

class TemplateBlockPolicy
{
    /**
     * @deprecated Preferir Gate {@see BlockPolicy::deleteForTemplate} vía `deleteTemplateBlock`.
     */
    public function delete(JwtUser $user, TemplateBlock $block): bool
    {
        $template = $block->template;

        return $template !== null
            && (new BlockPolicy)->deleteForTemplate($user, $template);
    }
}
