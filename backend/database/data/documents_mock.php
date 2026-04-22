<?php

/**
 * Documentos mock para local/testing.
 *
 * - template_id / template_version_id deben existir tras TemplateVersionsSeeder.
 * - created_by / owner_id deben existir en users_mock.php.
 * - study_* / module_id alineados con academic_hierarchy_mock.php cuando el catálogo esté sembrado (opcional).
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

return array_merge([
    [
        'id' => '77777777-7777-7777-7777-777777777701',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'template_version_id' => '66666666-6666-6666-6666-666666666601',
        'title' => 'Documento seed — borrador (plantilla global)',
        'study_type_id' => 'ST_FP',
        'study_id' => 'S_FP_DAW',
        'module_id' => 'M_DAW_DWES',
        'created_by' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'owner_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'status' => 'draft',
        'current_version' => 1,
        'submitted_at' => null,
        'published_at' => null,
    ],
    [
        'id' => '77777777-7777-7777-7777-777777777702',
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'template_version_id' => '66666666-6666-6666-6666-666666666602',
        'title' => 'Documento seed — borrador (plantilla por grupo)',
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'created_by' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'owner_id' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'status' => 'draft',
        'current_version' => 1,
        'submitted_at' => null,
        'published_at' => null,
    ],
], $programacionPack['documents'] ?? []);