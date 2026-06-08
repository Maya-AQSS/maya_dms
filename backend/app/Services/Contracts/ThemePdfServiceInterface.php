<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ThemePdfServiceInterface
{
    /**
     * Genera un PDF/UA-1 de muestra del theme (lorem ipsum) y devuelve los
     * bytes del PDF. Síncrono: el documento de muestra es de una página, por
     * lo que WeasyPrint responde en ~1-2s sin necesidad de cola.
     *
     * Lanza RuntimeException si WeasyPrint falla.
     */
    public function generateSample(string $themeId): string;
}
