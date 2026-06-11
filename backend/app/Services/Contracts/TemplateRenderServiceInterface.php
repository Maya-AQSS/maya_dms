<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface TemplateRenderServiceInterface
{
    /**
     * Devuelve el HTML themed de la plantilla — un "preview document"
     * sintético construido a partir de los `template_blocks` y su
     * `default_content`. Comparte el mismo Blade que el render de
     * documentos para que el aspecto sea idéntico entre vista previa de
     * plantilla y vista previa de documento.
     *
     * @param  bool  $previewMode  true → carga paged.js (preview en navegador).
     *
     * Lanza NotFoundHttpException si la plantilla no existe o no es visible.
     */
    public function renderHtml(string $templateId, bool $previewMode = false): string;

    /**
     * Renderiza el HTML de una versión histórica de la plantilla a partir del
     * contenido CONGELADO en su snapshot. Reconstruye los bloques vía
     * TemplateVersionBlockLayerResolver (misma lógica que el historial de versiones)
     * y los renderiza con el mismo pipeline que renderHtml. Los bloques ausentes del
     * snapshot no se incluyen (el snapshot define la estructura de esa versión).
     *
     * @param  string  $templateId  UUID de la plantilla.
     * @param  string  $versionId  UUID del entity_version publicado.
     * @param  bool  $previewMode  true → carga paged.js (preview en navegador).
     *
     * Lanza NotFoundHttpException si la plantilla o la versión no existen.
     */
    public function renderHtmlForVersion(string $templateId, string $versionId, bool $previewMode = false): string;
}
