<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Services\Contracts\DocumentPdfServiceInterface;
use App\Services\Contracts\DocumentRenderServiceInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Process;

/**
 * Genera PDF/UA-1 tagged usando el binario WeasyPrint instalado en el container
 * (apk + pip — ver maya_dms/backend/Dockerfile). El HTML lo produce
 * DocumentRenderService, lo que garantiza que el preview de navegador y el PDF
 * comparten exactamente el mismo Blade + CSS.
 *
 * En vez de depender de spatie/laravel-pdf, shelleamos directamente. Razones:
 *   - Una sola invocación de proceso, sin layer de Spatie que no aporta valor
 *     dado que sólo usamos un driver.
 *   - Control directo sobre el flag --pdf-variant pdf/ua-1 (requisito legal).
 *   - Cero deps composer nuevas. Si más adelante se quiere multi-driver
 *     (Browsershot/Gotenberg para casos no archivísticos), migrar a Spatie.
 */
class DocumentPdfService implements DocumentPdfServiceInterface
{
    /** Disco donde se persisten los PDFs (configurable por env si hiciera falta). */
    private const DISK = 'local';

    /** Subdirectorio raíz para los PDFs de documentos dentro del disco. */
    private const PREFIX = 'documents';

    /** Timeout duro del proceso WeasyPrint, segundos. */
    private const PROCESS_TIMEOUT = 60.0;

    public function __construct(
        private readonly DocumentRenderServiceInterface $renderer,
    ) {}

    public function generate(string $documentId): string
    {
        /** @var Document|null $document */
        $document = Document::query()->find($documentId);
        if ($document === null) {
            throw new NotFoundHttpException('Documento no encontrado.');
        }

        $html = $this->renderer->renderHtml($documentId);
        // current_version (alias seleccionado en el join del head EV) o 1 como fallback.
        $version = (int) ($document->getAttribute('current_version') ?? 1);
        $relative = sprintf('%s/%s/v%d/document.pdf', self::PREFIX, $documentId, $version);
        $absolute = Storage::disk(self::DISK)->path($relative);

        @mkdir(dirname($absolute), 0775, true);

        // weasyprint - <out> : lee HTML por stdin y escribe a $absolute.
        // --pdf-variant pdf/ua-1 fuerza estructura tagged + metadatos PDF/UA.
        $process = new Process([
            'weasyprint',
            '--encoding', 'utf-8',
            '--pdf-variant', 'pdf/ua-1',
            '-',
            $absolute,
        ]);
        $process->setInput($html);
        $process->setTimeout(self::PROCESS_TIMEOUT);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'WeasyPrint falló al generar PDF para documento '.$documentId.': '
                    .$process->getErrorOutput()
            );
        }

        return $relative;
    }
}
