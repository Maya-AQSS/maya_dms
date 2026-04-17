<?php

use App\Enums\TemplateVisibilityLevel;

/**
 * Datos mock de plantillas y revisores para local/testing.
 *
 * - Los user_id deben existir en database/data/users_mock.php
 * - Los team_id (FK a equipos) deben existir en database/data/teams_mock.php (si aplica)
 */
return [
    'templates' => [
        [
            'id' => '33333333-3333-3333-3333-333333333301',
            'name' => 'Plantilla mock — visibilidad global',
            'description' => 'Plantilla compartida a nivel global.',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_id' => null,
            'study_type_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => 'usr_direction_demo',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ],
        [
            'id' => '33333333-3333-3333-3333-333333333302',
            'name' => 'Plantilla mock — visibilidad por equipo',
            'description' => 'Plantilla visible para miembros del equipo seed.',
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'delivery_deadline' => null,
            'study_id' => null,
            'study_type_id' => null,
            'module_id' => null,
            'team_id' => '11111111-1111-1111-1111-111111111102',
            'created_by' => 'usr_hierarchy_fp_demo',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ],
    ],
    'template_reviewers' => [
        [
            'id' => '44444444-4444-4444-4444-444444444401',
            'template_id' => '33333333-3333-3333-3333-333333333301',
            'user_id' => 'usr_secretariat_demo',
            'stage' => 1,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444402',
            'template_id' => '33333333-3333-3333-3333-333333333301',
            'user_id' => 'usr_hierarchy_eso_demo',
            'stage' => 2,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444403',
            'template_id' => '33333333-3333-3333-3333-333333333302',
            'user_id' => 'usr_hierarchy_bach_demo',
            'stage' => 1,
        ],
    ],
];
