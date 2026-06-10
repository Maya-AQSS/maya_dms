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
     * @param  bool  $previewMode  Si true, el Blade carga paged.js para
     *                             simular CSS Paged Media en el navegador.
     *                             Para WeasyPrint debe quedar false (paged.js
     *                             confunde el motor y no es necesario).
     *
     * Lanza NotFoundHttpException si el documento no existe o no es visible.
     */
    public function renderHtml(string $documentId, bool $previewMode = false): string;

    /**
     * HTML de una versión histórica: contenido congelado del snapshot sobre la
     * estructura/tema de la plantilla viva. Ver implementación para el algoritmo
     * de fusión.
     *
     * @param  list<array<string, mixed>>  $snapshotBlocks  Bloques resueltos del snapshot.
     */
    public function renderHtmlForVersion(string $documentId, array $snapshotBlocks, bool $previewMode = false): string;
}
