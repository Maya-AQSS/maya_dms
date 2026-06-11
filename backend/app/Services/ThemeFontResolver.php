<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Alinea las métricas tipográficas del preview de navegador con las del PDF.
 *
 * WeasyPrint resuelve los `font-family` del theme vía fontconfig contra las
 * fuentes instaladas en el contenedor (whitelist `config/theme_fonts.php`).
 * El navegador, en cambio, resuelve contra las fuentes del equipo del usuario
 * — si "DejaVu Sans" no existe ahí, cae a Arial/Liberation (más estrechas) y
 * el texto envuelve en puntos distintos que en el PDF (portadas con cajas
 * absolutas: títulos que caben en 1 línea en preview pero ocupan 2 en el PDF).
 *
 * Este resolver mapea cada familia del whitelist a sus ficheros reales del
 * contenedor y los expone como `@font-face` con `data:` URI para el preview
 * (el iframe `blob:` no puede leer `file://` ni hacer fetch al backend). Así
 * el navegador maqueta con LOS MISMOS ficheros de fuente que WeasyPrint.
 *
 * En el render para PDF no se emite nada: fontconfig ya resuelve nativo.
 */
final class ThemeFontResolver
{
    /**
     * familia (minúsculas) => [peso CSS => fichero]. Los pesos '100 900' son
     * variable fonts (un único fichero cubre todo el rango).
     *
     * Espejo de `config/theme_fonts.php` + fuentes instaladas en el Dockerfile.
     */
    private const FAMILY_FILES = [
        'dejavu sans' => ['400' => '/usr/share/fonts/dejavu/DejaVuSans.ttf', '700' => '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf'],
        'dejavu serif' => ['400' => '/usr/share/fonts/dejavu/DejaVuSerif.ttf', '700' => '/usr/share/fonts/dejavu/DejaVuSerif-Bold.ttf'],
        'dejavu sans mono' => ['400' => '/usr/share/fonts/dejavu/DejaVuSansMono.ttf', '700' => '/usr/share/fonts/dejavu/DejaVuSansMono-Bold.ttf'],
        'liberation sans' => ['400' => '/usr/share/fonts/liberation/LiberationSans-Regular.ttf', '700' => '/usr/share/fonts/liberation/LiberationSans-Bold.ttf'],
        'liberation serif' => ['400' => '/usr/share/fonts/liberation/LiberationSerif-Regular.ttf', '700' => '/usr/share/fonts/liberation/LiberationSerif-Bold.ttf'],
        'liberation mono' => ['400' => '/usr/share/fonts/liberation/LiberationMono-Regular.ttf', '700' => '/usr/share/fonts/liberation/LiberationMono-Bold.ttf'],
        'roboto' => ['400' => '/usr/share/fonts/roboto/Roboto-Regular.ttf', '700' => '/usr/share/fonts/roboto/Roboto-Bold.ttf'],
        'roboto flex' => ['100 900' => '/usr/share/fonts/roboto-flex/RobotoFlex[GRAD,XOPQ,XTRA,YOPQ,YTAS,YTDE,YTFI,YTLC,YTUC,opsz,slnt,wdth,wght].ttf'],
        'roboto mono' => ['100 900' => '/usr/share/fonts/roboto-mono/RobotoMono[wght].ttf'],
        'open sans' => ['400' => '/usr/share/fonts/opensans/OpenSans-Regular.ttf', '700' => '/usr/share/fonts/opensans/OpenSans-Bold.ttf'],
        'noto sans' => ['400' => '/usr/share/fonts/noto/NotoSans-Regular.ttf', '700' => '/usr/share/fonts/noto/NotoSans-Bold.ttf'],
        'noto serif' => ['400' => '/usr/share/fonts/noto/NotoSerif-Regular.ttf', '700' => '/usr/share/fonts/noto/NotoSerif-Bold.ttf'],
        'droid sans' => ['400' => '/usr/share/fonts/droid/DroidSans.ttf', '700' => '/usr/share/fonts/droid/DroidSans-Bold.ttf'],
        'inter' => ['100 900' => '/usr/share/fonts/google/Inter.ttf'],
        'lora' => ['100 900' => '/usr/share/fonts/google/Lora.ttf'],
        'merriweather' => ['100 900' => '/usr/share/fonts/google/Merriweather.ttf'],
        'inconsolata' => ['400' => '/usr/share/fonts/inconsolata/Inconsolata-Regular.otf', '700' => '/usr/share/fonts/inconsolata/Inconsolata-Bold.otf'],
        'mononoki' => ['400' => '/usr/share/fonts/mononoki/mononoki-Regular.ttf', '700' => '/usr/share/fonts/mononoki/mononoki-Bold.ttf'],
    ];

    /**
     * Faces embebibles (`@font-face`) para las familias primarias de los
     * stacks CSS dados. Familias desconocidas o genéricas (`sans-serif`…) se
     * omiten; ficheros ausentes se omiten silenciosamente (el navegador caerá
     * al fallback del stack, igual que fontconfig).
     *
     * @param  list<string|null>  $stacks  valores de `font-family` del theme
     * @return list<array{family: string, weight: string, format: string, src: string}>
     */
    public static function embeddableFaces(array $stacks): array
    {
        $faces = [];
        $seen = [];

        foreach ($stacks as $stack) {
            $family = self::primaryFamily($stack);
            if ($family === null) {
                continue;
            }
            $key = mb_strtolower($family);
            if (isset($seen[$key]) || ! isset(self::FAMILY_FILES[$key])) {
                continue;
            }
            $seen[$key] = true;

            foreach (self::FAMILY_FILES[$key] as $weight => $file) {
                if (! is_file($file)) {
                    continue;
                }
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }
                $isOtf = str_ends_with(mb_strtolower($file), '.otf');

                $faces[] = [
                    'family' => $family,
                    'weight' => (string) $weight,
                    'format' => $isOtf ? 'opentype' : 'truetype',
                    'src' => 'data:font/'.($isOtf ? 'otf' : 'ttf').';base64,'.base64_encode($contents),
                ];
            }
        }

        return $faces;
    }

    /**
     * Primera familia no genérica de un stack CSS ("DejaVu Sans, Liberation
     * Sans, sans-serif" → "DejaVu Sans"). Null si el stack es nulo, vacío o
     * sólo contiene genéricas.
     */
    private static function primaryFamily(?string $stack): ?string
    {
        if ($stack === null || trim($stack) === '') {
            return null;
        }
        $first = trim((string) strtok($stack, ','));
        $first = trim($first, " \t\"'");
        if ($first === '') {
            return null;
        }
        $generic = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-monospace'];

        return in_array(mb_strtolower($first), $generic, true) ? null : $first;
    }
}
