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
];
