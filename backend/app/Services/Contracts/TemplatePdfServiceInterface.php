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
}
