<?php

declare(strict_types=1);

/**
 * Publicaciones mock de plantilla.
 *
 * {@see \Database\Seeders\TemplateVersionsSeeder} inserta las filas publicadas en `entity_versions`.
 * `entity_version_id` es el UUID estable usado por `documents.template_version_id`.
 *
 * El blocks_snapshot se construye en programaciones_didacticas_pack.php derivándolo
 * de los template_blocks (misma plantilla), alineado con
 * {@see \App\Services\TemplateService::publishWithSnapshot()}.
 */

$pack = require __DIR__ . '/programaciones_didacticas_pack.php';

return $pack['template_versions'];
