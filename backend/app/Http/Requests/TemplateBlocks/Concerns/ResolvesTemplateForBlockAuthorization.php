<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks\Concerns;

use App\Models\Template;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;

trait ResolvesTemplateForBlockAuthorization
{
    public function resolveTemplate(): Template
    {
        $template = $this->route('template');
        if ($template instanceof Template) {
            return $template;
        }

        $templateId = (string) $template;
        if ($templateId !== '') {
            return app(TemplateServiceInterface::class)->findOrFailWithoutCatalogScope($templateId);
        }

        $blockId = (string) $this->route('block');
        $blockDto = app(TemplateBlockServiceInterface::class)->findOrFail($blockId);

        return app(TemplateServiceInterface::class)->findOrFailWithoutCatalogScope($blockDto->templateId);
    }
}
