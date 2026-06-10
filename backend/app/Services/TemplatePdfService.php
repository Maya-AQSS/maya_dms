<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\TemplatePdfServiceInterface;
use App\Services\Contracts\TemplateRenderServiceInterface;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Genera el PDF/UA-1 de una plantilla con WeasyPrint, reutilizando el mismo HTML
 * que el preview (TemplateRenderService, sin paged.js). Igual que el PDF de
 * muestra de themes: síncrono y en memoria — la plantilla es un documento de
 * tamaño acotado, así que no compensa la infraestructura de colas del export
 * real de documentos.
 */
class TemplatePdfService implements TemplatePdfServiceInterface
{
    /** Timeout duro del proceso WeasyPrint, segundos. */
    private const PROCESS_TIMEOUT = 60;

    public function __construct(
        private readonly TemplateRenderServiceInterface $renderer,
    ) {}

    public function generateSample(string $templateId): string
    {
        // previewMode=false: HTML plano para WeasyPrint (sin paged.js).
        $html = $this->renderer->renderHtml($templateId, previewMode: false);

        // `weasyprint - -` lee el HTML por stdin y escribe el PDF por stdout.
        $result = Process::input($html)
            ->timeout(self::PROCESS_TIMEOUT)
            ->run([
                'weasyprint',
                '--encoding', 'utf-8',
                '--pdf-variant', 'pdf/ua-1',
                '-',
                '-',
            ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'WeasyPrint falló al generar el PDF de la plantilla '.$templateId.': '
                    .$result->errorOutput()
            );
        }

        return $result->output();
    }
}
