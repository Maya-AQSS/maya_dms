<?php

namespace App\Services\Contracts;

use App\Models\Template;

interface TemplateServiceInterface
{
    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template;
}
