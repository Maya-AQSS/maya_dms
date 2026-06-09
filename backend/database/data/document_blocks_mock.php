<?php

declare(strict_types=1);

/**
 * Bloques instanciados (document_blocks) de las programaciones didácticas reales del CEEDCV.
 *
 * El contenido viene parseado de los archivos .md formateados que viven en el workspace root:
 * - programacion_ciclo.md  → D0 (Ciclo ASIR)
 * - 25_26_DAW_0613_DWES.md → D1
 * - 25_26_DAW_1709_IPO I.md → D2
 * - 25_26_TL_0626_LAP.md   → D3
 * - 25_26_BATX1_PXSI1.md   → D4
 *
 * Solo se crean document_blocks para bloques `editable`, `modifiable` y `optional` con contenido.
 * Los bloques `locked` heredan el `default_content` del template_block (sin row aquí).
 */
$pack = require __DIR__.'/programaciones_didacticas_pack.php';

return $pack['document_blocks'];
