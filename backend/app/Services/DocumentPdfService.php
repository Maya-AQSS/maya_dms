<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\DocumentVersionDetailDto;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentPdfServiceInterface;
use App\Services\Contracts\DocumentRenderServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Support\WeasyPrintRunner;
use Illuminate\Support\Facades\Storage;

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
    private const PROCESS_TIMEOUT = 60;

    public function __construct(
        private readonly DocumentRenderServiceInterface $renderer,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentServiceInterface $documentService,
        private readonly WeasyPrintRunner $runner,
    ) {}

    public function generate(string $documentId, ?string $versionId = null): string
    {
        [$html, $version] = $this->buildHtml($documentId, $versionId);

        $relative = sprintf('%s/%s/v%d/document.pdf', self::PREFIX, $documentId, $version);
        $absolute = Storage::disk(self::DISK)->path($relative);

        Storage::disk(self::DISK)->makeDirectory(
            sprintf('%s/%s/v%d', self::PREFIX, $documentId, $version),
        );

        // weasyprint - <out> : lee HTML por stdin y escribe a $absolute.
        $this->runner->run($html, self::PROCESS_TIMEOUT, $absolute, 'para documento '.$documentId);

        return $relative;
    }

    public function generateBytes(string $documentId, ?string $versionId = null): string
    {
        [$html] = $this->buildHtml($documentId, $versionId);

        // `weasyprint - -` lee el HTML por stdin y escribe el PDF por stdout:
        // generación SÍNCRONA en memoria (igual que el PDF de muestra de themes),
        // sin tocar disco ni la infraestructura de colas/estado del export real.
        return $this->runner->run($html, self::PROCESS_TIMEOUT, '-', 'para documento '.$documentId);
    }

    /**
     * Construye el HTML themed (HEAD vivo o snapshot de versión) y el número de
     * versión para la ruta de salida.
     *
     * @return array{0: string, 1: int}
     */
    private function buildHtml(string $documentId, ?string $versionId): array
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($versionId !== null) {
            // Versión histórica: renderiza el snapshot congelado. El detalle ya
            // reconstruye `snapshot_data.blocks` (capas + fallback al JSON) para
            // filas en entity_versions y document_versions.
            /** @var DocumentVersionDetailDto $detail */
            $detail = $this->documentService->findDocumentVersionDetailOrFail($documentId, $versionId);
            $snapshotBlocks = is_array($detail->snapshotData['blocks'] ?? null)
                ? $detail->snapshotData['blocks']
                : [];

            return [
                $this->renderer->renderHtmlForVersion($documentId, $snapshotBlocks),
                $detail->versionNumber,
            ];
        }

        // current_version (alias del join del head EV) o 1 como fallback.
        return [
            $this->renderer->renderHtml($documentId),
            (int) $this->extractCurrentVersion($document),
        ];
    }

    /**
     * Extracts current version number from document model as scalar.
     */
    private function extractCurrentVersion(Document $document): int
    {
        return (int) ($document->getAttribute('current_version') ?? 1);
    }
}
