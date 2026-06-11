<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DocumentPdfServiceInterface
{
    /**
     * Genera el PDF/UA del documento en memoria (WeasyPrint a stdout) y
     * devuelve los bytes. Síncrono, efímero, sin tocar disco ni colas.
     * Igual al PDF de muestra de themes/templates.
     *
     * Lanza RuntimeException si WeasyPrint falla.
     */
    public function generateBytes(string $documentId, ?string $versionId = null): string;
}
