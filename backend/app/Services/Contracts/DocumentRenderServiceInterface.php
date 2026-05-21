<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DocumentRenderServiceInterface
{
    /**
     * Devuelve el HTML completo de un documento aplicando su theme (a través
     * de la plantilla anclada). Pensado para preview en navegador y para ser
     * pasado a WeasyPrint sin transformación adicional.
     *
     * Lanza NotFoundHttpException si el documento no existe o no es visible.
     */
    public function renderHtml(string $documentId): string;
}
