<?php

/**
 * Bloques instanciados mock (document_blocks).
 *
 * - document_id en documents_mock.php
 * - template_block_id en template_blocks_mock.php
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

return array_merge([
    [
        'id' => '88888888-8888-8888-8888-888888888801',
        'document_id' => '77777777-7777-7777-7777-777777777701',
        'template_block_id' => '55555555-5555-5555-5555-555555555501',
        'content' => ['type' => 'doc', 'content' => []],
        'is_filled' => false,
        'last_edited_by' => null,
        'locked_by' => null,
        'locked_at' => null,
        'sort_order' => 0,
    ],
    [
        'id' => '88888888-8888-8888-8888-888888888802',
        'document_id' => '77777777-7777-7777-7777-777777777702',
        'template_block_id' => '55555555-5555-5555-5555-555555555502',
        'content' => ['type' => 'doc', 'content' => []],
        'is_filled' => false,
        'last_edited_by' => null,
        'locked_by' => null,
        'locked_at' => null,
        'sort_order' => 0,
    ],
    [
        'id' => '88888888-8888-8888-8888-888888888803',
        'document_id' => '77777777-7777-7777-7777-777777777703',
        'template_block_id' => '55555555-5555-5555-5555-555555555511',
        'content' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => [
                    'textColor' => 'default',
                    'backgroundColor' => 'default',
                    'textAlignment' => 'left',
                ],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Programación didáctica del módulo DWES elaborada por docencia (seed). Enviada a validación: pendiente de Dirección y Secretaría.',
                    'styles' => [],
                ]],
                'children' => [],
            ]],
        ],
        'is_filled' => true,
        'last_edited_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'locked_by' => null,
        'locked_at' => null,
        'sort_order' => 0,
    ],
    [
        'id' => '88888888-8888-8888-8888-888888888804',
        'document_id' => '77777777-7777-7777-7777-777777777704',
        'template_block_id' => '55555555-5555-5555-5555-555555555511',
        'content' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => [
                    'textColor' => 'default',
                    'backgroundColor' => 'default',
                    'textAlignment' => 'left',
                ],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Segunda programación DWES de prueba (docente Bachillerato, seed). Pendiente de revisión por Dirección y Secretaría.',
                    'styles' => [],
                ]],
                'children' => [],
            ]],
        ],
        'is_filled' => true,
        'last_edited_by' => '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f',
        'locked_by' => null,
        'locked_at' => null,
        'sort_order' => 0,
    ],
    [
        'id' => '88888888-8888-8888-8888-888888888805',
        'document_id' => '77777777-7777-7777-7777-777777777705',
        'template_block_id' => '55555555-5555-5555-5555-555555555518',
        'content' => [
            'type' => 'doc',
            'content' => array_merge(
                require __DIR__.'/dwes_official_programacion_blocknote.php',
                [[
                    'type' => 'paragraph',
                    'props' => [
                        'textColor' => 'default',
                        'backgroundColor' => 'default',
                        'textAlignment' => 'left',
                    ],
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Nota del centro (docente FP, seed): adaptación local del modelo global; pendiente de Secretaría y Auditoría.',
                        'styles' => [],
                    ]],
                    'children' => [],
                ]],
            ),
        ],
        'is_filled' => true,
        'last_edited_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'locked_by' => null,
        'locked_at' => null,
        'sort_order' => 0,
    ],
], $programacionPack['document_blocks'] ?? []);