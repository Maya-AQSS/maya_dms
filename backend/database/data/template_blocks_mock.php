<?php

/**
 * Bloques mock de plantilla.
 * Los template_id deben existir en database/data/templates_mock.php.
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

$dwesGlobalOfficialContent = require __DIR__.'/dwes_official_programacion_blocknote.php';

return array_merge([
    [
        'id' => '55555555-5555-5555-5555-555555555501',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'title' => 'Título',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555502',
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'title' => 'Descripción',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555503',
        'template_id' => '33333333-3333-3333-3333-333333333303',
        'title' => 'Borrador personal',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555504',
        'template_id' => '33333333-3333-3333-3333-333333333304',
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
    [
        'id' => '55555555-5555-5555-5555-555555555505',
        'template_id' => '33333333-3333-3333-3333-333333333305',
        'title' => 'Programación 1º ESO',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555506',
        'template_id' => '33333333-3333-3333-3333-333333333306',
        'title' => 'Matemáticas — módulo',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555507',
        'template_id' => '33333333-3333-3333-3333-333333333307',
        'title' => 'Global II',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555508',
        'template_id' => '33333333-3333-3333-3333-333333333308',
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
    [
        'id' => '55555555-5555-5555-5555-555555555509',
        'template_id' => '33333333-3333-3333-3333-333333333309',
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
    [
        'id' => '55555555-5555-5555-5555-555555555510',
        'template_id' => '33333333-3333-3333-3333-333333333310',
        'title' => 'Ciclo DAW',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555511',
        'template_id' => '33333333-3333-3333-3333-333333333311',
        'title' => 'DWES',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555512',
        'template_id' => '33333333-3333-3333-3333-333333333312',
        'title' => 'Personal docente Bach',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555513',
        'template_id' => '33333333-3333-3333-3333-333333333313',
        'title' => 'Personal docente FP',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555514',
        'template_id' => '33333333-3333-3333-3333-333333333314',
        'title' => 'Personal Secretaría',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555515',
        'template_id' => '33333333-3333-3333-3333-333333333315',
        'title' => 'Personal Bach',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555516',
        'template_id' => '33333333-3333-3333-3333-333333333316',
        'title' => '2º ESO',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555517',
        'template_id' => '33333333-3333-3333-3333-333333333317',
        'title' => 'Inglés 1º ESO',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555518',
        'template_id' => '33333333-3333-3333-3333-333333333318',
        'title' => 'Programación didáctica — DWES (oficial, catálogo global)',
        'default_content' => $dwesGlobalOfficialContent,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555519',
        'template_id' => '33333333-3333-3333-3333-333333333319',
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
], $programacionPack['template_blocks']);
