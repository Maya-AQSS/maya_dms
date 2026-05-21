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
}
