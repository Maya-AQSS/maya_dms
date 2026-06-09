<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Repositories\Contracts\ThemeRepositoryInterface;

/**
 * Lógica de render de bloques compartida por DocumentRenderService y
 * TemplateRenderService (antes duplicada). Opera sobre una estructura
 * normalizada de bloque (arrays con `theme_id`/`apply_theme`), de modo que cada
 * service mapea su fuente de datos (modelo Eloquent o array de plantilla) a esa
 * forma común antes de delegar.
 *
 * Requiere que la clase consumidora exponga `$this->themeRepository`.
 */
trait BlockRenderSupport
{
    abstract protected function themeRepository(): ThemeRepositoryInterface;

    /**
     * Temas (palette/typography) referenciados por bloques cuyo `theme_id`
     * difiere del por defecto, para emitir CSS scopeado `[data-theme-id="X"]`.
     *
     * @param  list<array{theme_id: ?string, apply_theme: bool}>  $blocks
     * @return array<int, array{id: string, palette: array<string,mixed>, typography: array<string,mixed>}>
     */
    protected function resolveScopedBlockThemes(array $blocks, string $defaultThemeId): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $tid = $block['theme_id'] !== null ? (string) $block['theme_id'] : '';
            if ($block['apply_theme'] && $tid !== '' && $tid !== $defaultThemeId) {
                $ids[$tid] = true;
            }
        }

        return $this->themeRepository()->findScopedThemesByIds(array_keys($ids));
    }

    /**
     * Id de tema efectivo de un bloque para el atributo `data-theme-id`:
     *  - 'none' si el bloque no aplica tema (apply_theme=false).
     *  - el theme_id del bloque si difiere del por defecto.
     *  - '' (hereda :root) en otro caso.
     */
    protected function effectiveThemeId(?string $themeId, bool $applyTheme, string $defaultThemeId): string
    {
        if (! $applyTheme) {
            return 'none';
        }
        $tid = $themeId !== null ? (string) $themeId : '';

        return ($tid !== '' && $tid !== $defaultThemeId) ? $tid : '';
    }

    /**
     * Clases CSS de la sección de un bloque según su maquetación.
     */
    protected function blockSectionClasses(string $type, bool $pageBreakAfter, bool $applyTheme): string
    {
        $classes = 'doc-block doc-block--'.$type;
        if ($pageBreakAfter) {
            $classes .= ' doc-block--page-break-after';
        }
        if (! $applyTheme) {
            $classes .= ' doc-block--no-theme';
        }

        return $classes;
    }

    /**
     * Envuelve el HTML interno de un bloque en su `<section>` con los atributos
     * de maquetación + el ancla `id="block-{anchorId}"` para el índice.
     */
    protected function wrapBlockSection(
        string $classes,
        string $anchorId,
        string $blockId,
        string $type,
        string $themeId,
        bool $pageBreakAfter,
        string $inner,
    ): string {
        return sprintf(
            '<section class="%s" id="block-%s" data-block-id="%s" data-block-type="%s" data-theme-id="%s"%s>%s</section>',
            $classes,
            e($anchorId),
            e($blockId),
            e($type),
            e($themeId),
            $pageBreakAfter ? ' data-page-break-after="true"' : '',
            $inner,
        );
    }
}
