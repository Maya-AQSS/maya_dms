<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TemplateRepository implements TemplateRepositoryInterface
{
    /**
     * Indica si el usuario es creador o revisor asignado de la plantilla.
     * Usado para control de acceso al historial de auditoría.
     */
    public function isCreatorOrReviewer(string $templateId, string $userId): bool
    {
        $isCreator = DB::table('templates')
            ->where('id', $templateId)
            ->where('created_by', $userId)
            ->exists();

        if ($isCreator) {
            return true;
        }

        return DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->exists();
    }
}
