<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\ThemeRenderServiceInterface;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Render del paso de Verificación de un theme. Construye un documento sintético
 * (lorem ipsum) y lo pasa por el mismo Blade `documents.render` que los
 * documentos y plantillas reales, de modo que la previsualización aplique
 * exactamente la misma paleta, tipografía, layout e imágenes que el PDF final.
 */
class ThemeRenderService implements ThemeRenderServiceInterface
{
    public function __construct(
        private readonly ThemeRepositoryInterface $themeRepository,
    ) {}

    public function renderHtml(string $themeId, bool $previewMode = false): string
    {
        $theme = $this->resolveTheme($themeId);

        return View::make('documents.render', [
            'document' => [
                'id' => 'theme-'.$themeId,
                'title' => (string) ($theme['brand_name'] ?? 'Tema'),
                'subject' => 'Previsualización del tema',
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $this->loremBody($theme),
            ],
            'theme' => $theme,
            'preview_mode' => $previewMode,
        ])->render();
    }

    /**
     * Cuerpo lorem ipsum sólo si el layout define un bloque content_slot
     * (es decir, hay un área donde se inserta el contenido del documento).
     * Sin content_slot el theme es puramente decorativo y el cuerpo va vacío.
     */
    private function loremBody(array $theme): string
    {
        $regions = $theme['layout']['regions'] ?? [];
        $hasContentSlot = false;
        foreach ((array) $regions as $region) {
            if (($region['type'] ?? null) === 'content_slot') {
                $hasContentSlot = true;
                break;
            }
        }

        if (! $hasContentSlot) {
            return '';
        }

        return <<<'HTML'
            <h1>Lorem ipsum dolor sit amet</h1>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
            <h2>Duis aute irure dolor</h2>
            <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore
            eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in
            culpa qui officia deserunt mollit anim id est laborum.</p>
            <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium
            doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore
            veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>
            HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTheme(string $themeId): array
    {
        $themeDto = $this->themeRepository->findThemeResolvedById($themeId);
        if ($themeDto === null) {
            throw new NotFoundHttpException('Theme no encontrado.');
        }

        return [
            'palette' => $themeDto->palette ?: DocumentConstants::DEFAULT_THEME['palette'],
            'typography' => $themeDto->typography ?: DocumentConstants::DEFAULT_THEME['typography'],
            'layout' => $themeDto->layout ?: DocumentConstants::DEFAULT_THEME['layout'],
            'accessibility' => $themeDto->accessibility ?: DocumentConstants::DEFAULT_THEME['accessibility'],
            'brand_name' => $themeDto->brandName,
        ];
    }
}
