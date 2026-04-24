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
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'I. Identificacion y Contextualizacion: ambito (Comunicacion / Cientifico-Tecnologico / Social), nivel (I o II) y perfil del alumnado adulto (situacion laboral, motivaciones y conciliacion).', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'II. Concrecion Curricular: competencias especificas vinculadas al perfil de salida y saberes basicos priorizando utilidad practica.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'III. Metodologia y Organizacion: andragogia, aprendizaje autonomo, tutorizacion y organizacion temporal (presencial, semipresencial o distancia).', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'IV. Evaluacion y Calificacion: instrumentos (pruebas de nivel, portafolios, tareas online) y ponderacion de examenes y trabajos practicos.', 'styles' => []]],
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
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'I. Introduccion y Contexto: materia, curso (1o o 2o) y vinculacion explicita con EvAU.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'II. Elementos del Curriculo (LOMLOE): competencias especificas, criterios de evaluacion y saberes basicos organizados por bloques.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'III. Situaciones de Aprendizaje: retos complejos y transversalidad entre materias (ej. Filosofia y Literatura).', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'IV. Evaluacion y Recuperacion: criterios de calificacion (ej. 80 por ciento examenes y 20 por ciento trabajos) y plan de recuperacion.', 'styles' => []]],
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
                'default_content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'I. Datos del Modulo: modulo (nombre y codigo), ciclo y resultados de aprendizaje (RA) extraidos del Real Decreto del titulo.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'II. Desarrollo de Unidades de Trabajo (UT): titulo, RA asociados y duracion por unidad.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'III. Metodologia (Entorno Profesional): ABP y retos tecnicos que simulan contexto laboral; uso de taller, laboratorio de redes y aula de simulacion.', 'styles' => []]],
                            'children' => [],
                        ],
                        [
                            'type' => 'paragraph',
                            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                            'content' => [['type' => 'text', 'text' => 'IV. Evaluacion de Competencias: criterios de evaluacion por cada RA, resultados minimos para Apto y coordinacion con la empresa (dualidad) cuando aplique.', 'styles' => []]],
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
