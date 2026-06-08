<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ThemeRenderServiceInterface
{
    /**
     * Devuelve el HTML del paso de verificación de un theme: aplica paleta,
     * tipografía, layout e imágenes sobre un documento sintético con lorem
     * ipsum en el área de contenido (si el layout tiene un bloque content_slot).
     * Reutiliza el mismo Blade `documents.render` para fidelidad total.
     *
     * @param  bool  $previewMode  true → el Blade carga paged.js para paginar
     *                             en el navegador; false → HTML plano para WeasyPrint.
     *
     * Lanza NotFoundHttpException si el theme no existe.
     */
    public function renderHtml(string $themeId, bool $previewMode = false): string;
}
