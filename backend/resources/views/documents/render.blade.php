@php
    /*
     * Render del documento (HTML para preview navegador + entrada a WeasyPrint
     * para PDF/UA).
     *
     * El theme puede tener un layout configurable por el editor de rejilla
     * (12 cols × 52 rows sobre A4). Cada region tiene `grid: {x,y,w,h,z}` +
     * `type` + `props`. Aquí lo traducimos a:
     *   - Margins de @page calculadas desde el `content_slot` (define dónde
     *     fluye el cuerpo del documento).
     *   - Una capa fija `<div class="theme-overlay">` con `position: fixed` —
     *     WeasyPrint la repite en TODAS las páginas, así que los bloques de
     *     cabecera/pie/marca de agua aparecen en cada página automáticamente.
     *
     * Si el theme NO tiene grid blocks (theme legacy o sin layout
     * personalizado), caemos al chrome estándar de @page con `string-set` y
     * `@top-left/right` + `@bottom-center` — el comportamiento original.
     *
     * PDF/UA: la overlay lleva `role="presentation"` (artifact en el árbol
     * estructural). El texto que repita encabezados/pie no se duplica en el
     * tagged content — sólo el cuerpo del documento contribuye a la
     * estructura tagged. Esto es exactamente lo que pide PDF/UA-1 para
     * contenido decorativo / paginal.
     */

    $regions = $theme['layout']['regions'] ?? [];
    $gridBlocks = [];
    foreach ($regions as $r) {
        if (is_array($r) && isset($r['grid']) && is_array($r['grid'])) {
            $gridBlocks[] = $r;
        }
    }

    $contentSlot = null;
    foreach ($gridBlocks as $b) {
        if (($b['type'] ?? '') === 'content_slot') {
            $contentSlot = $b;
            break;
        }
    }
    $hasGridLayout = count($gridBlocks) > 0;

    // A4 portrait — mismo modelo que usa el editor frontend.
    $pageWidthCm = 21.0;
    $pageHeightCm = 29.7;
    $cols = 12;
    $rows = 52;
    $colW = $pageWidthCm / $cols;       // 1.75 cm
    $rowH = $pageHeightCm / $rows;      // ~0.5712 cm

    $defaultMargin = $theme['layout']['page']['margin_cm'] ?? [
        'top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2,
    ];

    if ($contentSlot !== null) {
        $g = $contentSlot['grid'];
        $marginTop    = max(0.0, (float) $g['y']                          * $rowH);
        $marginLeft   = max(0.0, (float) $g['x']                          * $colW);
        $marginRight  = max(0.0, (float) ($cols - $g['x'] - $g['w'])      * $colW);
        $marginBottom = max(0.0, (float) ($rows - $g['y'] - $g['h'])      * $rowH);
    } else {
        $marginTop    = (float) ($defaultMargin['top']    ?? 2.5);
        $marginRight  = (float) ($defaultMargin['right']  ?? 2);
        $marginBottom = (float) ($defaultMargin['bottom'] ?? 2.5);
        $marginLeft   = (float) ($defaultMargin['left']   ?? 2);
    }

    // Filtrar el content_slot del overlay — no es chrome, es el cuerpo.
    $overlayBlocks = [];
    foreach ($gridBlocks as $b) {
        if (($b['type'] ?? '') !== 'content_slot') {
            $overlayBlocks[] = $b;
        }
    }

    /**
     * Resuelve un asset del theme a una URL `file://` legible por
     * WeasyPrint (que corre en el mismo container y comparte filesystem).
     * Si el path no existe (theme sin asset subido) devuelve null.
     */
    $assetUrl = function (?string $relativePath): ?string {
        if (! $relativePath) return null;
        $absolute = storage_path('app/themes/'.ltrim($relativePath, '/'));
        return file_exists($absolute) ? ('file://'.$absolute) : null;
    };

    $logoFileUrl       = $assetUrl($theme['assets']['logo_path']             ?? null);
    $backgroundFileUrl = $assetUrl($theme['assets']['background_image_path'] ?? null);
    $watermarkFileUrl  = $assetUrl($theme['assets']['watermark_path']        ?? null);

    // Helper para formatear cm con 4 decimales sin notación científica.
    $cm = fn (float $v) => number_format($v, 4, '.', '').'cm';
@endphp
<!doctype html>
<html lang="{{ $document['lang'] ?? 'es' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    <meta name="description" content="{{ $document['subject'] ?? '' }}">
    <meta name="author" content="{{ $theme['accessibility']['author'] ?? 'CEEDCV' }}">
    <style>
        /* ─── Tokens del Theme (CSS variables) ─── */
        :root {
            --color-primary: {{ $theme['palette']['primary'] ?? '#0b5394' }};
            --color-secondary: {{ $theme['palette']['secondary'] ?? '#666666' }};
            --color-text: {{ $theme['palette']['text'] ?? '#1a1a1a' }};
            --color-bg: {{ $theme['palette']['background'] ?? '#ffffff' }};
            --color-accent: {{ $theme['palette']['accent'] ?? '#f59e0b' }};
            --font-heading: {{ $theme['typography']['heading_font'] ?? 'sans-serif' }};
            --font-body: {{ $theme['typography']['body_font'] ?? 'sans-serif' }};
            --base-size: {{ $theme['typography']['base_size_pt'] ?? 11 }}pt;
            --line-height: {{ $theme['typography']['line_height'] ?? 1.5 }};
        }

        /* ─── CSS Paged Media ─── */
        h1.doc-title {
            string-set:
                doc-title content(),
                brand-name "{{ $theme['brand_name'] ?? 'CEEDCV' }}";
        }

        @page {
            size: {{ $theme['layout']['page']['size'] ?? 'A4' }};
            margin: {{ $cm($marginTop) }} {{ $cm($marginRight) }} {{ $cm($marginBottom) }} {{ $cm($marginLeft) }};

            @if (! $hasGridLayout)
                /* Chrome estándar — sólo cuando no hay layout de rejilla. */
                @top-left {
                    content: string(brand-name);
                    font-family: var(--font-heading);
                    font-weight: 700;
                    font-size: 9pt;
                    color: var(--color-primary);
                    border-bottom: 1pt solid var(--color-primary);
                    padding-bottom: 0.2cm;
                    width: 100%;
                }
                @top-right {
                    content: string(doc-title);
                    font-family: var(--font-body);
                    font-size: 9pt;
                    color: var(--color-secondary);
                    border-bottom: 1pt solid var(--color-primary);
                    padding-bottom: 0.2cm;
                }
                @bottom-center {
                    content: "Página " counter(page) " de " counter(pages);
                    font-family: var(--font-body);
                    font-size: 9pt;
                    color: var(--color-secondary);
                }
            @endif
        }

        @if (! $hasGridLayout)
            @page :first {
                @top-left { content: none; }
                @top-right { content: none; }
            }
        @endif

        /* ─── Estilos base ─── */
        html, body {
            font-family: var(--font-body);
            font-size: var(--base-size);
            line-height: var(--line-height);
            color: var(--color-text);
            background: var(--color-bg);
            margin: 0;
            padding: 0;
        }

        @media screen {
            body {
                padding: {{ $cm($marginTop) }} {{ $cm($marginRight) }} {{ $cm($marginBottom) }} {{ $cm($marginLeft) }};
                max-width: 21cm;
                margin: 1rem auto;
                box-shadow: 0 0 8px rgba(0,0,0,.08);
                border-radius: 4px;
                position: relative;
            }
        }

        header.page-header {
            display: {{ $hasGridLayout ? 'none' : 'flex' }};
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--color-primary);
            padding-bottom: 0.3cm;
            font-size: 9pt;
            color: var(--color-secondary);
        }
        header.page-header .brand {
            font-family: var(--font-heading);
            font-weight: 700;
            color: var(--color-primary);
        }
        @media print { header.page-header { display: none; } }

        h1 { font-family: var(--font-heading); color: var(--color-primary); font-size: 22pt; margin: 0 0 0.4cm; page-break-after: avoid; }
        h2 { font-family: var(--font-heading); color: var(--color-primary); font-size: 16pt; margin-top: 0.8cm; page-break-after: avoid; }
        h3 { font-family: var(--font-heading); color: var(--color-primary); font-size: 13pt; margin-top: 0.6cm; page-break-after: avoid; }
        h4, h5, h6 { font-family: var(--font-heading); color: var(--color-primary); margin-top: 0.4cm; page-break-after: avoid; }
        p { margin: 0 0 0.4cm; }
        ul, ol { margin: 0 0 0.4cm 1cm; }
        blockquote {
            border-left: 4px solid var(--color-accent);
            padding-left: 0.4cm;
            margin: 0.4cm 0;
            color: var(--color-secondary);
        }
        pre { background: #f4f4f4; padding: 0.3cm; border-radius: 3px; overflow-x: auto; }
        code { font-family: ui-monospace, "Courier New", monospace; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 0.4cm 0; }
        thead { display: table-header-group; }
        th, td { border: 1px solid var(--color-secondary); padding: 0.2cm 0.3cm; text-align: left; font-size: 10pt; }
        th { background: var(--color-primary); color: var(--color-bg); font-family: var(--font-heading); }
        img, figure { max-width: 100%; }
        figcaption { font-size: 9pt; color: var(--color-secondary); text-align: center; }

        /* ─── Overlay del grid del theme (chrome repetido en cada página) ─── */
        @if ($hasGridLayout)
            /* En WeasyPrint, `position: fixed` repite el elemento en cada página
               del PDF — perfecto para cabeceras, pies y marcas de agua del theme.
               Excluimos el overlay del árbol estructural (`aria-hidden`) para
               que el texto repetido no contamine el tagged content de PDF/UA. */
            .theme-overlay {
                position: fixed;
                top: -{{ $cm($marginTop) }};
                left: -{{ $cm($marginLeft) }};
                width: {{ $cm($pageWidthCm) }};
                height: {{ $cm($pageHeightCm) }};
                pointer-events: none;
                z-index: 1;
                @if ($backgroundFileUrl)
                    background-image: url('{{ $backgroundFileUrl }}');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                @endif
            }
            .theme-overlay .blk {
                position: absolute;
                overflow: hidden;
                box-sizing: border-box;
            }
            .theme-overlay .blk-text,
            .theme-overlay .blk-meta { display: flex; align-items: center; font-family: var(--font-body); }
            .theme-overlay .blk-image,
            .theme-overlay .blk-logo { display: flex; align-items: center; justify-content: center; }
            .theme-overlay .blk-image img,
            .theme-overlay .blk-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .theme-overlay .blk-watermark {
                display: flex; align-items: center; justify-content: center;
                font-family: var(--font-heading); font-weight: 900; font-size: 60pt;
                color: var(--color-secondary); text-transform: uppercase; letter-spacing: 0.05em;
            }
            /* `main` debe estar por encima del overlay para que el cuerpo capture
               clicks/selección y el contenido no quede tapado visualmente. */
            main { position: relative; z-index: 2; }
        @endif
    </style>
</head>
<body>

@if ($hasGridLayout)
    <div class="theme-overlay" role="presentation" aria-hidden="true">
        @foreach ($overlayBlocks as $b)
            @php
                $g = $b['grid'];
                $p = $b['props'] ?? [];
                $left   = $cm((float) $g['x'] * $colW);
                $top    = $cm((float) $g['y'] * $rowH);
                $width  = $cm((float) $g['w'] * $colW);
                $height = $cm((float) $g['h'] * $rowH);
                $z      = (int) ($g['z'] ?? 1);
                $type   = $b['type'] ?? 'text';
            @endphp

            @switch($type)
                @case('text')
                    @php
                        $align = $p['align'] ?? 'left';
                        if (! in_array($align, ['left','center','right'], true)) $align = 'left';
                    @endphp
                    <div class="blk blk-text"
                         style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};
                                font-size:{{ (float) ($p['size'] ?? 9) }}pt;
                                color:{{ $p['color'] ?? '#333' }};
                                text-align:{{ $align }};">
                        {{ $p['text'] ?? '' }}
                    </div>
                    @break

                @case('logo')
                    @if ($logoFileUrl)
                        <div class="blk blk-logo"
                             style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};">
                            <img src="{{ $logoFileUrl }}" alt="">
                        </div>
                    @endif
                    @break

                @case('image')
                    @php
                        $asset = $p['asset'] ?? 'background';
                        $url = match ($asset) {
                            'logo' => $logoFileUrl,
                            'watermark' => $watermarkFileUrl,
                            default => $backgroundFileUrl,
                        };
                    @endphp
                    @if ($url)
                        <div class="blk blk-image"
                             style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};">
                            <img src="{{ $url }}" alt="">
                        </div>
                    @endif
                    @break

                @case('page_number')
                    @php
                        $align = $p['align'] ?? 'right';
                        if (! in_array($align, ['left','center','right'], true)) $align = 'right';
                    @endphp
                    <div class="blk blk-meta"
                         style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};
                                font-size:9pt; color:var(--color-secondary);
                                text-align:{{ $align }};">
                        @if (($p['format'] ?? 'page-of-pages') === 'page-of-pages')
                            <span style="display:block;width:100%">Página <span class="pn">_</span> de <span class="pt">_</span></span>
                        @else
                            <span style="display:block;width:100%">Página <span class="pn">_</span></span>
                        @endif
                    </div>
                    @break

                @case('date')
                    @php
                        $align = $p['align'] ?? 'left';
                        if (! in_array($align, ['left','center','right'], true)) $align = 'left';
                    @endphp
                    <div class="blk blk-meta"
                         style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};
                                font-size:9pt; color:var(--color-secondary);
                                text-align:{{ $align }};">
                        {{ ($p['format'] ?? 'short') === 'long'
                            ? \Illuminate\Support\Carbon::now()->locale($theme['accessibility']['language'] ?? 'es')->isoFormat('D [de] MMMM [de] YYYY')
                            : \Illuminate\Support\Carbon::now()->format('d/m/Y') }}
                    </div>
                    @break

                @case('watermark')
                    <div class="blk blk-watermark"
                         style="left:{{ $left }};top:{{ $top }};width:{{ $width }};height:{{ $height }};z-index:{{ $z }};
                                opacity:{{ max(0, min(1, (float) ($p['opacity'] ?? 0.15))) }};
                                transform:rotate({{ (int) ($p['rotate'] ?? -30) }}deg);">
                        {{ $p['text'] ?? 'BORRADOR' }}
                    </div>
                    @break
            @endswitch
        @endforeach
    </div>

    {{-- Counter para page number en CSS Paged Media: WeasyPrint reemplaza
         estos elementos en cada página via `target-counter` o `counter(page)`.
         Como están dentro de un fixed que se repite, podemos usar el contador
         de página actual con un pseudo-elemento. Implementación: usamos
         `content: counter(page)` sobre los spans .pn/.pt vía CSS counters. --}}
    <style>
        .theme-overlay .blk-meta .pn::before { content: counter(page); }
        .theme-overlay .blk-meta .pn { font-size: 0; }
        .theme-overlay .blk-meta .pn::before { font-size: 9pt; }
        .theme-overlay .blk-meta .pt::before { content: counter(pages); }
        .theme-overlay .blk-meta .pt { font-size: 0; }
        .theme-overlay .blk-meta .pt::before { font-size: 9pt; }
    </style>
@else
    <header class="page-header" role="banner">
        <span class="brand">{{ $theme['brand_name'] ?? 'CEEDCV' }}</span>
        <span>{{ $document['title'] }}</span>
    </header>
@endif

<main role="main">
    <h1 class="doc-title">{{ $document['title'] }}</h1>
    @if (! empty($document['subject']))
        <p class="doc-subject">{{ $document['subject'] }}</p>
    @endif

    {{-- Contenido del documento (HTML sanitizado por BlockNoteHtmlRenderer en backend) --}}
    {!! $document['body_html'] !!}
</main>

</body>
</html>
