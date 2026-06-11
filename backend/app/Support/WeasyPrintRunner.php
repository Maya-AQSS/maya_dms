<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Encapsula la invocación del proceso WeasyPrint con la configuración común
 * a todos los callers (Template, Theme, Document PDF services).
 *
 * Flags fijos (idénticos en los 3 servicios originales):
 *   --encoding utf-8
 *   --pdf-variant pdf/ua-1
 *   stdin = '-' (HTML vía stdin)
 *   stdout = configurable (defecto '-' = stdout; o ruta absoluta para fichero)
 *
 * El timeout se pasa por parámetro para preservar las diferencias por caller:
 *   - TemplatePdfService: 60 s
 *   - ThemePdfService:    30 s
 *   - DocumentPdfService: 60 s
 */
final class WeasyPrintRunner
{
    /**
     * Ejecuta WeasyPrint y devuelve el PDF.
     *
     * @param  string  $html  HTML de entrada (se pasa por stdin).
     * @param  int  $timeoutSeconds  Timeout duro del proceso.
     * @param  string  $output  Ruta de salida o '-' para stdout (default).
     * @param  string  $callerContext  Identificador del caller para el mensaje de
     *                                 error en logs (p. ej. "plantilla abc-123").
     * @return string Bytes del PDF cuando $output === '-'; cadena vacía si es fichero.
     *
     * @throws RuntimeException si el proceso termina con código de salida ≠ 0.
     */
    public function run(
        string $html,
        int $timeoutSeconds,
        string $output = '-',
        string $callerContext = '',
    ): string {
        $result = Process::input($html)
            ->timeout($timeoutSeconds)
            ->run([
                'weasyprint',
                '--encoding', 'utf-8',
                '--pdf-variant', 'pdf/ua-1',
                '-',
                $output,
            ]);

        if ($result->failed()) {
            $context = $callerContext !== '' ? ' '.$callerContext : '';
            throw new RuntimeException(
                'WeasyPrint falló al generar el PDF'.$context.': '.$result->errorOutput()
            );
        }

        return $output === '-' ? $result->output() : '';
    }
}
