<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface TemplatePdfServiceInterface
{
    /**
     * Genera el PDF/UA-1 de una plantilla (mismo HTML themed que el preview) y
     * devuelve los bytes. Síncrono, en memoria (WeasyPrint a stdout): mismo
     * patrón que el PDF de muestra de themes, sin cola ni estado.
     *
     * Lanza RuntimeException si WeasyPrint falla.
     */
    public function generateSample(string $templateId): string;

    /**
     * Genera el PDF/UA-1 del snapshot de una versión histórica de la plantilla.
     * Reconstruye los bloques congelados vía TemplateVersionBlockLayerResolver y
     * renderiza con el mismo pipeline que generateSample. Síncrono, en memoria.
     *
     * Lanza RuntimeException si WeasyPrint falla.
     * Lanza NotFoundHttpException si la versión no pertenece a la plantilla.
     */
    public function generateForVersion(string $templateId, string $versionId): string;
}
