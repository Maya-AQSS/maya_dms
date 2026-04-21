<?php

/**
 * Versiones publicadas mock de plantilla.
 *
 * blocks_snapshot replica el shape que genera {@see TemplateService::publishWithSnapshot()}.
 * Los template_id deben existir en database/data/templates_mock.php.
 */
return [
    [
        'id' => '66666666-6666-6666-6666-666666666601',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'version_number' => 1,
        'blocks_snapshot' => [
            [
                'id' => '55555555-5555-5555-5555-555555555501',
                'type' => 'heading',
                'title' => 'Título',
                'default_content' => null,
                'block_state' => 'editable',
                'mandatory' => true,
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
                'type' => 'paragraph',
                'title' => 'Descripción',
                'default_content' => ['type' => 'doc', 'content' => []],
                'block_state' => 'editable',
                'mandatory' => false,
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
                'type' => 'heading',
                'title' => 'Objetivos del módulo',
                'default_content' => null,
                'block_state' => 'editable',
                'mandatory' => true,
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
                'type' => 'paragraph',
                'title' => 'Criterios de evaluación',
                'default_content' => ['type' => 'doc', 'content' => []],
                'block_state' => 'editable',
                'mandatory' => false,
                'sort_order' => 0,
            ],
        ],
        'changelog' => 'Publicación inicial para creación de documentos por módulo',
        'published_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'published_at' => null,
    ],
];
