<?php

declare(strict_types=1);

/**
 * Favoritos demo alineados con programaciones_didacticas_pack.php y maya_dev_users.php.
 */

$devUsers = require __DIR__ . '/maya_dev_users.php';
$u = static fn (string $key): string => $devUsers[$key];

$T0 = 'aa000000-0000-4000-8000-000000000000';
$T1 = 'aa000001-0000-4000-8000-000000000000';
$T2 = 'aa000002-0000-4000-8000-000000000000';

$D1 = 'dd000001-0000-4000-8000-000000000000';
$D4 = 'dd000004-0000-4000-8000-000000000000';

return [
    'favorite_templates' => [
        ['user_id' => $u('direccion'), 'template_id' => $T0],
        ['user_id' => $u('jefe_e_fp'), 'template_id' => $T1],
        ['user_id' => $u('jefe_e_bach'), 'template_id' => $T2],
    ],
    'favorite_documents' => [
        ['user_id' => $u('docente_i'), 'document_id' => $D1],
        ['user_id' => $u('docente_b'), 'document_id' => $D4],
    ],
];
