<?php

/**
 * Bloques mock de plantilla.
 * Los template_id deben existir en database/data/templates_mock.php.
 */
return [
    [
        'id' => '55555555-5555-5555-5555-555555555501',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'type' => 'heading',
        'title' => 'Título',
        'default_content' => null,
        'block_state' => 'editable',
        'mandatory' => true,
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555502',
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'type' => 'paragraph',
        'title' => 'Descripción',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'mandatory' => false,
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555503',
        'template_id' => '33333333-3333-3333-3333-333333333303',
        'type' => 'heading',
        'title' => 'Borrador personal',
        'default_content' => null,
        'block_state' => 'editable',
        'mandatory' => true,
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555504',
        'template_id' => '33333333-3333-3333-3333-333333333304',
        'type' => 'paragraph',
        'title' => 'Contenido ESO (tipo)',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'mandatory' => false,
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555505',
        'template_id' => '33333333-3333-3333-3333-333333333305',
        'type' => 'heading',
        'title' => 'Programación 1º ESO',
        'default_content' => null,
        'block_state' => 'editable',
        'mandatory' => true,
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555506',
        'template_id' => '33333333-3333-3333-3333-333333333306',
        'type' => 'paragraph',
        'title' => 'Matemáticas — módulo',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'mandatory' => false,
        'sort_order' => 0,
    ],
];
