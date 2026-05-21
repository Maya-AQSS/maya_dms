<!doctype html>
<html lang="{{ $document['lang'] ?? 'es' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    {{-- Metadatos PDF/UA. --}}
    <meta name="description" content="{{ $document['subject'] ?? '' }}">
    <meta name="author" content="{{ $document['author'] ?? 'CEEDCV' }}">
    <style>
        /* ─── Tokens del Theme ─── */
        :root {
            --color-primary: {{ $theme['palette']['primary'] }};
            --color-secondary: {{ $theme['palette']['secondary'] }};
            --color-text: {{ $theme['palette']['text'] }};
            --color-bg: {{ $theme['palette']['background'] }};
            --font-heading: {{ $theme['typography']['heading_font'] }};
            --font-body: {{ $theme['typography']['body_font'] }};
        }

        /* ─── CSS Paged Media (WeasyPrint) ─── */
        /* string-set genera el running header desde el body sin duplicar
           contenido en el flujo del documento — WeasyPrint marca @page como
           Artifact automáticamente (cumple PDF/UA). */
        h1.doc-title { string-set: doc-title content(), brand-name "{{ $theme['brand_name'] ?? 'CEEDCV' }}"; }

        @page {
            size: A4;
            margin: 2.5cm 2cm 2.5cm 2cm;

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
            @bottom-right {
                content: "{{ $document['ref'] ?? '' }}";
                font-family: var(--font-body);
                font-size: 8pt;
                color: var(--color-secondary);
            }
        }

        @page :first {
            @top-left { content: none; }
            @top-right { content: none; }
        }

        html, body {
            font-family: var(--font-body);
            font-size: 11pt;
            line-height: 1.5;
            color: var(--color-text);
            background: var(--color-bg);
            margin: 0;
            padding: 0;
        }

        h1 {
            font-family: var(--font-heading);
            font-size: 22pt;
            color: var(--color-primary);
            margin: 0 0 0.4cm 0;
            page-break-after: avoid;
        }
        h2 {
            font-family: var(--font-heading);
            font-size: 14pt;
            color: var(--color-primary);
            margin-top: 0.8cm;
            page-break-after: avoid;
        }
        p { margin: 0 0 0.4cm 0; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.4cm 0;
            page-break-inside: auto;
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
        caption {
            font-weight: 600;
            text-align: left;
            padding-bottom: 0.2cm;
        }

        .cover {
            page-break-after: always;
            text-align: center;
            padding-top: 5cm;
        }
        .cover .doc-ref {
            font-size: 10pt;
            color: var(--color-secondary);
            margin-top: 1cm;
        }
    </style>
</head>
<body>

<section class="cover" aria-label="Portada del documento">
    <h1 class="doc-title">{{ $document['title'] }}</h1>
    <p>{{ $document['subject'] }}</p>
    <p class="doc-ref">Ref.: {{ $document['ref'] }} · {{ $document['date'] }}</p>
</section>

<main>
    <h2>Resumen</h2>
    <p>Este documento es una prueba de concepto del pipeline de generación de PDF accesible
        (PDF/UA‑1) en el ecosistema Maya. El motor utilizado es WeasyPrint sobre un Blade
        renderizado con CSS Paged Media.</p>

    <h2>Tabla de ejemplo</h2>
    <table>
        <caption>Calificaciones del estudiante</caption>
        <thead>
            <tr>
                <th id="col-module" scope="col">Módulo</th>
                <th id="col-call" scope="col">Convocatoria</th>
                <th id="col-grade" scope="col">Calificación</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($document['rows'] ?? []) as $row)
            <tr>
                <td headers="col-module">{{ $row['module'] }}</td>
                <td headers="col-call">{{ $row['call'] }}</td>
                <td headers="col-grade">{{ $row['grade'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Observaciones</h2>
    <p>El theme aplicado controla colores, tipografías y la marca visible en la cabecera de
        cada página. Los bloques de contenido se inyectan en el slot principal definido por el
        theme. Este POC sólo valida la cadena Blade → WeasyPrint → PDF/UA.</p>
</main>

</body>
</html>
