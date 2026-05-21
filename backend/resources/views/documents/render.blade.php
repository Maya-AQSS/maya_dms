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

        /* ─── CSS Paged Media (WeasyPrint para PDF; @media screen para navegador) ───
           string-set genera el running header desde h1.doc-title sin duplicar
           contenido en el flujo del documento. WeasyPrint marca el contenido
           de @page como Artifact, cumpliendo el requisito de PDF/UA-1. */
        h1.doc-title {
            string-set:
                doc-title content(),
                brand-name "{{ $theme['brand_name'] ?? 'CEEDCV' }}";
        }

        @page {
            size: {{ $theme['layout']['page']['size'] ?? 'A4' }};
            margin:
                {{ ($theme['layout']['page']['margin_cm']['top'] ?? 2.5) }}cm
                {{ ($theme['layout']['page']['margin_cm']['right'] ?? 2) }}cm
                {{ ($theme['layout']['page']['margin_cm']['bottom'] ?? 2.5) }}cm
                {{ ($theme['layout']['page']['margin_cm']['left'] ?? 2) }}cm;

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
        }

        @page :first {
            @top-left { content: none; }
            @top-right { content: none; }
        }

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

        /* En navegador (no print) damos un wrapper visual estilo "hoja A4". */
        @media screen {
            body {
                padding: 2.5cm 2cm;
                max-width: 21cm;
                margin: 1rem auto;
                box-shadow: 0 0 8px rgba(0,0,0,.08);
                border-radius: 4px;
            }
        }

        /* En @media screen mostramos un header visual en la página; en print
           lo ocultamos para no duplicar el running header del @page. */
        header.page-header {
            display: flex;
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
        pre {
            background: #f4f4f4;
            padding: 0.3cm;
            border-radius: 3px;
            overflow-x: auto;
        }
        code { font-family: ui-monospace, "Courier New", monospace; font-size: 10pt; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.4cm 0;
        }
        thead { display: table-header-group; }
        th, td {
            border: 1px solid var(--color-secondary);
            padding: 0.2cm 0.3cm;
            text-align: left;
            font-size: 10pt;
        }
        th {
            background: var(--color-primary);
            color: var(--color-bg);
            font-family: var(--font-heading);
        }
        img, figure { max-width: 100%; }
        figcaption { font-size: 9pt; color: var(--color-secondary); text-align: center; }

        .checklist { list-style: none; padding-left: 0; }
        .doc-title { margin-bottom: 0.6cm; }
    </style>
</head>
<body>

<header class="page-header" role="banner">
    <span class="brand">{{ $theme['brand_name'] ?? 'CEEDCV' }}</span>
    <span>{{ $document['title'] }}</span>
</header>

<main role="main">
    <h1 class="doc-title">{{ $document['title'] }}</h1>
    @if (!empty($document['subject']))
        <p class="doc-subject">{{ $document['subject'] }}</p>
    @endif

    {{-- Contenido del documento (HTML sanitizado por BlockNoteHtmlRenderer en backend) --}}
    {!! $document['body_html'] !!}
</main>

</body>
</html>
