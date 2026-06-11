<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates\Concerns;

use App\Models\Template;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesTemplateForAuthorization
{
    private ?Template $resolvedTemplate = null;

    private ?bool $resolvedDirectAccess = null;

    public function resolveTemplate(): Template
    {
        if ($this->resolvedTemplate !== null) {
            return $this->resolvedTemplate;
        }

        [$template, $directAccess] = $this->resolveTemplateWithAccessContext();
        $this->resolvedTemplate = $template;
        $this->resolvedDirectAccess = $directAccess;

        return $template;
    }

    public function hasDirectTemplateAccess(): bool
    {
        $this->resolveTemplate();

        return $this->resolvedDirectAccess ?? true;
    }

    /**
     * @return array{0: Template, 1: bool}
     */
    private function resolveTemplateWithAccessContext(): array
    {
        $id = (string) $this->route('template');
        $service = app(TemplateServiceInterface::class);

        try {
            return [$service->findModelOrFail($id), true];
        } catch (ModelNotFoundException) {
            $template = $service->findOrFailWithoutCatalogScope($id);
            if (! $service->hasPublishedSnapshot($template->id)) {
                abort(404);
            }

            return [$template, false];
        }
    }
}
