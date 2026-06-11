<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\ThemePdfServiceInterface;
use App\Services\Contracts\ThemeRenderServiceInterface;
use App\Support\WeasyPrintRunner;

/**
 * Genera el PDF/UA-1 de muestra de un theme con WeasyPrint, reutilizando el
 * mismo HTML que el preview (sin paged.js). A diferencia del export de
 * documentos, es síncrono y devuelve los bytes en memoria: el documento de
 * muestra es de una sola página, así que no compensa la infraestructura de
 * colas/estado del export real.
 */
class ThemePdfService implements ThemePdfServiceInterface
{
    /** Timeout duro del proceso WeasyPrint, segundos. */
    private const PROCESS_TIMEOUT = 30;

    public function __construct(
        private readonly ThemeRenderServiceInterface $renderer,
        private readonly WeasyPrintRunner $runner,
    ) {}

    public function generateSample(string $themeId): string
    {
        // previewMode=false: HTML plano para WeasyPrint (sin paged.js).
        $html = $this->renderer->renderHtml($themeId, previewMode: false);

        // `weasyprint - -` lee el HTML por stdin y escribe el PDF por stdout.
        return $this->runner->run(
            $html,
            self::PROCESS_TIMEOUT,
            '-',
            'de muestra del theme '.$themeId,
        );
    }
}
