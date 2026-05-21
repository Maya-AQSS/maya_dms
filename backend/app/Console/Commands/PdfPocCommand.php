<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;

/**
 * POC Phase 0: renderiza una Blade view con CSS Paged Media y la pasa por
 * WeasyPrint para producir un PDF/UA-1 tagged. No depende de Spatie laravel-pdf;
 * sirve para validar la toolchain antes de instalarlo en Phase 4.
 *
 * Salida: storage/app/poc/document.pdf
 */
final class PdfPocCommand extends Command
{
    protected $signature = 'pdf:poc {--out=poc/document.pdf}';

    protected $description = 'Genera un PDF/UA POC con WeasyPrint a partir de la Blade resources/views/pdf/_poc.blade.php';

    public function handle(): int
    {
        $relative = (string) $this->option('out');
        $outAbs = storage_path('app/'.ltrim($relative, '/'));
        @mkdir(dirname($outAbs), 0775, true);

        $theme = [
            'brand_name' => 'CEEDCV',
            'palette' => [
                'primary' => '#0b5394',
                'secondary' => '#666666',
                'text' => '#1a1a1a',
                'background' => '#ffffff',
            ],
            'typography' => [
                'heading_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
                'body_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
            ],
        ];

        $document = [
            'title' => 'Certificado Académico',
            'subject' => 'Acreditación de calificaciones — curso 2025/2026',
            'author' => 'CEEDCV',
            'ref' => 'CEEDCV-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'date' => now()->format('d/m/Y'),
            'lang' => 'es',
            'rows' => [
                ['module' => 'Programación', 'call' => 'Ordinaria', 'grade' => 'Sobresaliente (9)'],
                ['module' => 'Bases de Datos', 'call' => 'Ordinaria', 'grade' => 'Notable (8)'],
                ['module' => 'Entornos de Desarrollo', 'call' => 'Ordinaria', 'grade' => 'Notable (7)'],
            ],
        ];

        $html = View::make('pdf._poc', compact('theme', 'document'))->render();

        // WeasyPrint lee HTML por stdin y escribe PDF en stdout (-) → archivo via redirección.
        // --pdf-variant pdf/ua-1 fuerza estructura tagged + metadatos PDF/UA.
        $process = new Process([
            'weasyprint',
            '--encoding', 'utf-8',
            '--pdf-variant', 'pdf/ua-1',
            '-',     // stdin
            $outAbs, // out path
        ]);
        $process->setInput($html);
        $process->setTimeout(60.0);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('WeasyPrint falló: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $this->info('PDF generado: '.$outAbs);
        $this->line('Tamaño: '.filesize($outAbs).' bytes');

        // Hint para validación accesible:
        $this->line('');
        $this->line('Validar con verapdf (host):');
        $this->line('  docker run --rm -v "$PWD/storage/app:/in" verapdf/cli --profile ua1 /in/'.$relative);

        return self::SUCCESS;
    }
}
