<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks\Concerns;

use App\Models\Template;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Support\Collection;

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

    /**
     * Ids de plantilla distintos a los que pertenece el conjunto de bloques.
     *
     * @param  list<string>  $blockIds
     * @return list<string>
     */
    public function templateIdsForBlockIds(array $blockIds): array
    {
        $blocks = app(TemplateBlockServiceInterface::class)->findBlocksByIdsOrFail($blockIds);

        return array_values(array_unique(array_map(
            static fn ($dto): string => $dto->templateId,
            $blocks,
        )));
    }

    /**
     * Resuelve las plantillas (indexadas por id) de un conjunto de ids.
     *
     * @param  list<string>  $templateIds
     * @return Collection<string, Template>
     */
    public function resolveTemplatesByIds(array $templateIds): Collection
    {
        return app(TemplateServiceInterface::class)->findManyByIds($templateIds);
    }
}
