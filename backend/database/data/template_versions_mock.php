<?php

/**
 * Datos fuente de publicaciones mock de plantilla (solo consumidos para construir `entity_versions`).
 *
 * {@see \Database\Seeders\TemplateVersionsSeeder} inserta las filas publicadas en `entity_versions`.
 * Opcional `entity_version_id`: UUID fijo de la publicación (ancla en `documents.template_version_id`).
 * La clave `id` por fila es solo referencia estable en datos de prueba (ya no existe tabla `template_versions`).
 * Los template_id deben existir en database/data/templates_mock.php.
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

/**
 * Construye un blocks_snapshot alineado con {@see \App\Services\TemplateService::publishWithSnapshot()}
 * a partir de los bloques actuales en template_blocks_mock.php (misma plantilla).
 */
$buildTemplateVersionSnapshotFromBlocks = static function (string $templateId): array {
    /** @var list<array<string, mixed>> $blocks */
    $blocks = require __DIR__.'/template_blocks_mock.php';

    $subset = array_values(array_filter(
        $blocks,
        static fn (array $b): bool => (string) ($b['template_id'] ?? '') === $templateId
    ));

    usort(
        $subset,
        static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
    );

    return array_map(
        static fn (array $b): array => [
            'id' => $b['id'],
            'title' => $b['title'],
            'description' => $b['description'] ?? null,
            'default_content' => $b['default_content'],
            'block_state' => $b['block_state'],
            'sort_order' => $b['sort_order'],
        ],
        $subset
    );
};

$snapshotPublishedSecretariaPersonal = $buildTemplateVersionSnapshotFromBlocks('33333333-3333-3333-3333-333333333319');
$snapshotSyllabusDwesEsqueleto = $buildTemplateVersionSnapshotFromBlocks('33333333-3333-3333-3333-333333333320');

return array_merge([
    [
        'id' => '66666666-6666-6666-6666-666666666607',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000607',
        'template_id' => '33333333-3333-3333-3333-333333333304',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555504',
                'title' => 'Programacion base ESPA (Personas Adultas)',
                'description' => 'Guia de edicion para docencia ESPA: adapta este bloque al contexto real del grupo adulto, concreta necesidades de conciliacion y empleabilidad, y sustituye ejemplos genericos por evidencias del centro. Mantener siempre relacion entre competencia, situacion de aprendizaje y criterio de calificacion.',
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Unidad Didactica: La palabra en el trabajo. Contexto: alumnado adulto, con personas trabajando o en busqueda activa de empleo. Competencia especifica: producir textos orales y escritos con coherencia y adecuacion en ambitos profesionales.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Saberes basicos: estructura del Curriculum Vitae y Carta de Presentacion; comprension de textos administrativos basicos (nominas, contratos); cortesia linguistica en entrevista de trabajo.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Situacion de aprendizaje: simulacion de una entrevista laboral. El alumnado redacta su CV real y realiza un role-play aplicando registros formales adecuados.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Criterio de calificacion: 40 por ciento tarea online (CV), 40 por ciento prueba presencial (comprension lectora), 20 por ciento participacion en foro o clase.', 'styles' => []]],
                            'children' => [],
                        ],
                    ],
                ],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicacion inicial plantilla base por tipo ST_ESPA',
        'published_by' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666608',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000608',
        'template_id' => '33333333-3333-3333-3333-333333333308',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555508',
                'title' => 'Programacion base Bachillerato',
                'description' => 'Guia de edicion para docencia Bachillerato: actualizar materia, curso y nivel de dificultad segun grupo real. Alinear de forma explicita criterios de evaluacion con formato EvAU y evidencias que se pediran en examen.',
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Unidad Didactica: Campo Gravitatorio (Fisica, 2o Bachillerato). Contexto: alumnado con alta presion academica orientado a EvAU y acceso universitario.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Competencia especifica: interpretar interacciones gravitatorias con fisica clasica y ley de gravitacion universal de Newton. Saberes basicos: leyes de Kepler, intensidad y potencial gravitatorio, energia orbital y velocidad de escape.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Situacion de aprendizaje: Mision a Marte. Calculo de velocidad de escape y orbita de transferencia, analizando consumo energetico en un escenario tipo EvAU.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Criterio de calificacion: 80 por ciento examenes teoricos y problemas (formato EvAU), 10 por ciento practicas de laboratorio o simuladores, 10 por ciento actitud y trabajo diario.', 'styles' => []]],
                            'children' => [],
                        ],
                    ],
                ],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicacion inicial plantilla base por tipo ST_BACH',
        'published_by' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666609',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000609',
        'template_id' => '33333333-3333-3333-3333-333333333309',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555509',
                'title' => 'Programacion base FP (Ciclos Medio/Superior)',
                'description' => 'Guia de edicion para docencia FP: personalizar este bloque con resultados de aprendizaje oficiales del modulo (BOE/DOG/BOJA), detallar entorno tecnico real del aula y definir criterios verificables de funcionamiento, documentacion y optimizacion.',
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Unidad de trabajo: Virtualizacion y Contenedores. Contexto: futuros tecnicos ASIR/DAW que deben desplegar entornos para empresa. RA3: instala software de virtualizacion y configura maquinas virtuales, analizando su funcionamiento.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Saberes basicos: hipervisores Type 1 y Type 2; redes virtuales NAT, Bridge y Host-only; introduccion a Docker (imagenes, contenedores y volumenes).', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Reto profesional: Infraestructura para una Startup. Levantar servidor web, base de datos y balanceador con Docker, con comunicacion interna entre servicios y base de datos no expuesta externamente.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'Criterio de evaluacion: funcionamiento tecnico del despliegue 60 por ciento, documentacion tecnica 30 por ciento, optimizacion de recursos 10 por ciento.', 'styles' => []]],
                            'children' => [],
                        ],
                    ],
                ],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicacion inicial plantilla base por tipo ST_FP',
        'published_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666601',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000601',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555501',
                'title' => 'Título',
                'description' => 'Guía para el revisor: comprueba que el bloque de portada o identificación sea inequívoco, incluya curso escolar y referencia al centro (seed).',
                'default_content' => null,
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Versión inicial (seed)',
        'published_by' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'published_at' => null, // lo rellena el seeder con Carbon::now()
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666602',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000602',
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555502',
                'title' => 'Descripción',
                'description' => 'Guía para el revisor: valida alcance del documento, público destinatario y coherencia con la visibilidad por equipo definida en la plantilla.',
                'default_content' => ['type' => 'doc', 'content' => []],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Versión inicial (seed)',
        'published_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666603',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000603',
        'template_id' => '33333333-3333-3333-3333-333333333306',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555506',
                'title' => 'Objetivos del módulo',
                'description' => 'Guía para el revisor: exige objetivos medibles, alineación con competencias del módulo y referencia explícita al currículo autonómico.',
                'default_content' => null,
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicación inicial para creación de documentos por módulo',
        'published_by' => 'cf8bb92a-0417-4a4c-918a-08dd3fd69165',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666604',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000604',
        'template_id' => '33333333-3333-3333-3333-333333333311',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555511',
                'title' => 'Criterios de evaluación',
                'description' => 'Guía para el revisor: comprueba ponderaciones, instrumentos y criterios observables alineados con el departamento y el RD del módulo.',
                'default_content' => ['type' => 'doc', 'content' => []],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicación inicial para creación de documentos por módulo',
        'published_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666605',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000605',
        'template_id' => '33333333-3333-3333-3333-333333333318',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555518',
                'title' => 'Programación didáctica — DWES (oficial, catálogo global)',
                'description' => 'Guía para el revisor: plantilla oficial de catálogo global DWES — verifica RA del BOE, temporalización y criterios de evaluación verificables.',
                'default_content' => require __DIR__.'/dwes_official_programacion_blocknote.php',
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicación oficial — programación DWES (catálogo global, Dirección)',
        'published_by' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666606',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000606',
        'template_id' => '33333333-3333-3333-3333-333333333319',
        'version_number' => 1,
        'blocks_snapshot' => $snapshotPublishedSecretariaPersonal,
        'changelog' => 'Publicación inicial (plantilla personal Secretaría)',
        'published_by' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'published_at' => null,
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666610',
        'entity_version_id' => 'a0000000-0000-4000-8000-000000000610',
        'template_id' => '33333333-3333-3333-3333-333333333320',
        'version_number' => 1,
        'blocks_snapshot' => $snapshotSyllabusDwesEsqueleto,
        'changelog' => 'Publicación inicial — esqueleto programación didáctica DWES.',
        'published_by' => '848dc299-240e-4a75-9d8e-f0a04089309d',
        'published_at' => null,
    ],
], $programacionPack['template_versions']);
