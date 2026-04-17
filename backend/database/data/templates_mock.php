<?php

use App\Enums\TemplateVisibilityLevel;

/**
 * Datos mock de plantillas y revisores para local/testing.
 *
 * - Los user_id deben existir en database/data/users_mock.php
 * - Los team_id (FK a equipos) deben existir en database/data/teams_mock.php (si aplica)
 * - IDs de jerarquía (study_type_id, study_id, module_id) alineados con
 *   {@see database/data/academic_hierarchy_mock.php}
 *
 * Keycloak / JWT (docentes): el scope global de {@see \App\Models\Template} filtra por
 * `study_type_ids`, `study_ids`, `module_ids` del token (y equipos vía team_members).
 * Clientes de realm distintos pueden mapear esos claims para probar visibilidad sin tocar código.
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
        [
            'id' => '33333333-3333-3333-3333-333333333303',
            'name' => 'Plantilla mock — visibilidad personal (Ana)',
            'description' => 'Solo la creadora (y quien tenga catálogo ampliado vía permisos) la ve. JWT: sin claims de ámbito requeridos.',
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_id' => null,
            'study_type_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => 'usr_ana_martinez',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ],
        [
            'id' => '33333333-3333-3333-3333-333333333304',
            'name' => 'Plantilla mock — visibilidad por tipo de estudio (ESO)',
            'description' => 'Visible si el JWT incluye study_type_ids con ST_ESO (p. ej. cliente/realm docente ESO).',
            'visibility_level' => TemplateVisibilityLevel::StudyType->value,
            'delivery_deadline' => null,
            'study_id' => null,
            'study_type_id' => 'ST_ESO',
            'module_id' => null,
            'team_id' => null,
            'created_by' => 'usr_direction_demo',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ],
        [
            'id' => '33333333-3333-3333-3333-333333333305',
            'name' => 'Plantilla mock — visibilidad por estudio (1º ESO)',
            'description' => 'Visible con study_ids que incluyan S_ESO_1 (programación concreta).',
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_id' => 'S_ESO_1',
            'study_type_id' => 'ST_ESO',
            'module_id' => null,
            'team_id' => null,
            'created_by' => 'usr_secretariat_demo',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ],
        [
            'id' => '33333333-3333-3333-3333-333333333306',
            'name' => 'Plantilla mock — visibilidad por módulo (Matemáticas 1º ESO)',
            'description' => 'Visible con module_ids que incluyan M_MAT_1. Útil para clientes Keycloak por módulo.',
            'visibility_level' => TemplateVisibilityLevel::Module->value,
            'delivery_deadline' => null,
            'study_id' => 'S_ESO_1',
            'study_type_id' => 'ST_ESO',
            'module_id' => 'M_MAT_1',
            'team_id' => null,
            'created_by' => 'usr_maria_garcia',
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'parallel',
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
        [
            'id' => '44444444-4444-4444-4444-444444444404',
            'template_id' => '33333333-3333-3333-3333-333333333303',
            'user_id' => 'usr_juan_rodriguez',
            'stage' => 1,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444405',
            'template_id' => '33333333-3333-3333-3333-333333333304',
            'user_id' => 'usr_hierarchy_eso_demo',
            'stage' => 1,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444406',
            'template_id' => '33333333-3333-3333-3333-333333333305',
            'user_id' => 'usr_hierarchy_eso_demo',
            'stage' => 1,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444407',
            'template_id' => '33333333-3333-3333-3333-333333333305',
            'user_id' => 'usr_juan_rodriguez',
            'stage' => 2,
        ],
        [
            'id' => '44444444-4444-4444-4444-444444444408',
            'template_id' => '33333333-3333-3333-3333-333333333306',
            'user_id' => 'usr_julia_sanchez',
            'stage' => 1,
        ],
    ],
];
