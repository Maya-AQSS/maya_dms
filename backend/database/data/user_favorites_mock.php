<?php

declare(strict_types=1);

/**
 * Favoritos demo alineados con programaciones_didacticas_pack.php y maya_dev_users.php.
 *
 * Plantillas: favorito sobre entity_versions publicadas (template_version_id).
 */
$devUsers = require __DIR__.'/maya_dev_users.php';
$u = static fn (string $key): string => $devUsers[$key];

$EV_T0_PUB = 'eeb00000-0000-4000-8000-000000000000';
$EV_T1_PUB = 'eeb00000-0000-4000-8000-000000000001';
$EV_T2_PUB = 'eeb00000-0000-4000-8000-000000000002';

$D1 = 'dd000001-0000-4000-8000-000000000000';
$D4 = 'dd000004-0000-4000-8000-000000000000';

return [
    'favorite_templates' => [
        ['user_id' => $u('direccion'), 'template_version_id' => $EV_T0_PUB],
        ['user_id' => $u('jefe_e_fp'), 'template_version_id' => $EV_T1_PUB],
        ['user_id' => $u('jefe_e_bach'), 'template_version_id' => $EV_T2_PUB],
    ],
    'favorite_documents' => [
        ['user_id' => $u('docente_i'), 'document_id' => $D1],
        ['user_id' => $u('docente_b'), 'document_id' => $D4],
    ],
];
