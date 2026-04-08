<?php

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\Template;

/**
 * Segregación de funciones (SoD) en plantillas.
 *
 * El creador de la plantilla no puede actuar como revisor/aprobador del mismo artefacto.
 */
class TemplatePolicy
{
    /**
     * Revisión / aprobación en el flujo de plantilla.
     */
    public function review(JwtUser $user, Template $template): bool
    {
        return $user->getAuthIdentifier() !== $template->created_by;
    }
}
