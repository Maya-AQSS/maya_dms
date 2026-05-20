<?php

declare(strict_types=1);

/**
 * Bloques de plantilla (programaciones didácticas reales del CEEDCV).
 *
 * Los template_id deben existir en database/data/templates_mock.php.
 * El contenido y los block_state se definen en programaciones_didacticas_pack.php.
 *
 * Estados (BlockState):
 * - locked: contenido fijo (texto común CEEDCV)
 * - editable: contenido obligatorio rellenado por el docente del módulo
 * - modifiable: contenido por defecto con placeholders en MAYÚSCULAS sustituibles
 * - optional: bloque opcional (se puede eliminar del documento)
 */

$pack = require __DIR__ . '/programaciones_didacticas_pack.php';

return $pack['template_blocks'];
