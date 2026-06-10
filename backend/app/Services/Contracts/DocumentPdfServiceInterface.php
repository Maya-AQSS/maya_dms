<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DocumentPdfServiceInterface
{
    /**
     * Genera el PDF/UA del documento aplicando su theme y lo guarda en el
     * disco `local` bajo `documents/{id}/v{version}/document.pdf`. Devuelve
     * la ruta relativa al disco.
     *
     * Lanza RuntimeException si WeasyPrint falla. Idempotente: sobreescribe
     * la ruta si el documento se re-exporta.
     *
     * Si se pasa `$versionId`, genera el PDF de esa versión histórica
     * renderizando su snapshot congelado en lugar del HEAD vivo; la ruta usa
     * el número de versión del snapshot.
     */
    public function generate(string $documentId, ?string $versionId = null): string;
}
