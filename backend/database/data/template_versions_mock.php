<?php

/**
 * Versiones publicadas mock de plantilla.
 *
 * blocks_snapshot replica el shape que genera {@see TemplateService::publishWithSnapshot()}.
 * Los template_id deben existir en database/data/templates_mock.php.
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

return array_merge([
    [
        'id' => '66666666-6666-6666-6666-666666666607',
        'template_id' => '33333333-3333-3333-3333-333333333304',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555504',
                'title' => 'Programacion base ESPA (Personas Adultas)',
                'description' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                        'content' => [[
                            'type' => 'text',
                            'text' => 'Guia de edicion para docencia ESPA: adapta este bloque al contexto real del grupo adulto, concreta necesidades de conciliacion y empleabilidad, y sustituye ejemplos genericos por evidencias del centro. Mantener siempre relacion entre competencia, situacion de aprendizaje y criterio de calificacion.',
                            'styles' => [],
                        ]],
                        'children' => [],
                    ]],
                ],
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
        'template_id' => '33333333-3333-3333-3333-333333333308',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555508',
                'title' => 'Programacion base Bachillerato',
                'description' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                        'content' => [[
                            'type' => 'text',
                            'text' => 'Guia de edicion para docencia Bachillerato: actualizar materia, curso y nivel de dificultad segun grupo real. Alinear de forma explicita criterios de evaluacion con formato EvAU y evidencias que se pediran en examen.',
                            'styles' => [],
                        ]],
                        'children' => [],
                    ]],
                ],
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
        'template_id' => '33333333-3333-3333-3333-333333333309',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555509',
                'title' => 'Programacion base FP (Ciclos Medio/Superior)',
                'description' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                        'content' => [[
                            'type' => 'text',
                            'text' => 'Guia de edicion para docencia FP: personalizar este bloque con resultados de aprendizaje oficiales del modulo (BOE/DOG/BOJA), detallar entorno tecnico real del aula y definir criterios verificables de funcionamiento, documentacion y optimizacion.',
                            'styles' => [],
                        ]],
                        'children' => [],
                    ]],
                ],
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
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555501',
                'title' => 'Título',
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
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555502',
                'title' => 'Descripción',
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
        'template_id' => '33333333-3333-3333-3333-333333333306',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555506',
                'title' => 'Objetivos del módulo',
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
        'template_id' => '33333333-3333-3333-3333-333333333311',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555511',
                'title' => 'Criterios de evaluación',
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
        'template_id' => '33333333-3333-3333-3333-333333333318',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555518',
                'title' => 'Programación didáctica — DWES (oficial, catálogo global)',
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
        'template_id' => '33333333-3333-3333-3333-333333333319',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555519',
                'title' => 'Descripción (plantilla personal Secretaría, publicada)',
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => [
                                'textColor' => 'default',
                                'backgroundColor' => 'default',
                                'textAlignment' => 'left',
                            ],
                            'content' => [[
                                'type' => 'text',
                                'text' => 'Plantilla de referencia publicada por Secretaría (seed). Los documentos generados se validan con Dirección y Auditoría según la configuración de revisores.',
                                'styles' => [],
                            ]],
                            'children' => [],
                        ],
                    ],
                ],
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicación inicial (plantilla personal Secretaría)',
        'published_by' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'published_at' => null,
    ],
], $programacionPack['template_versions']);
