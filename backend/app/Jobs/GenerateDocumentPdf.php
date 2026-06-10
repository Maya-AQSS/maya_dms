<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Contracts\DocumentPdfServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job asíncrono para generar el PDF/UA del documento. Encolado a Redis/RabbitMQ
 * (según QUEUE_CONNECTION). Cachea el estado en `pdf-export:{id}` para que el
 * frontend pueda hacer polling al endpoint /export-status.
 *
 * Idempotente: si se encola dos veces, sobreescribe la misma ruta de salida.
 */
class GenerateDocumentPdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /** Caduca tras 30 minutos: tiempo holgado para Redis cleanup. */
    private const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        public readonly string $documentId,
        public readonly string $requestedBy,
        public readonly ?string $versionId = null,
    ) {}

    public function handle(DocumentPdfServiceInterface $service): void
    {
        $this->setStatus(['state' => 'processing', 'document_id' => $this->documentId, 'version_id' => $this->versionId]);

        try {
            $relative = $service->generate($this->documentId, $this->versionId);

            $this->setStatus([
                'state' => 'ready',
                'document_id' => $this->documentId,
                'version_id' => $this->versionId,
                'path' => $relative,
                'finished_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateDocumentPdf failed', [
                'document_id' => $this->documentId,
                'version_id' => $this->versionId,
                'requested_by' => $this->requestedBy,
                'error' => $e->getMessage(),
            ]);
            $this->setStatus([
                'state' => 'failed',
                'document_id' => $this->documentId,
                'version_id' => $this->versionId,
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);
            throw $e;
        }
    }

    public function statusCacheKey(): string
    {
        return self::keyFor($this->documentId, $this->versionId);
    }

    public static function keyFor(string $documentId, ?string $versionId = null): string
    {
        return $versionId !== null
            ? 'pdf-export:'.$documentId.':v'.$versionId
            : 'pdf-export:'.$documentId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function setStatus(array $payload): void
    {
        Cache::put($this->statusCacheKey(), $payload, self::CACHE_TTL_SECONDS);
    }
}
