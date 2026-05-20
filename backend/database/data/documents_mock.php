<?php

declare(strict_types=1);

/**
 * Documentos mock (programaciones didácticas reales del CEEDCV, curso 2025-26).
 *
 * El pack consumido define 5 documentos:
 * - D0: Programación de ciclo ASIR (study_id=8, sin module_id)
 * - D1: DWES — Desarrollo Web en Entorno Servidor (study_id=7 DAW, module_id=7_2)
 * - D2: IPO I — Itinerari Personal per a l'Ocupabilitat I (study_id=7 DAW, module_id=7_7)
 * - D3: LAP — Logística de Aprovisionamiento (study_id=15 TIL, module_id=15_8)
 * - D4: PXSI1 — Programación, Redes y Sistemas Informáticos I (study_id=3 BCT, module_id=3_9, study_type=NG)
 *
 * `template_version_id` es el UUID de la publicación en `entity_versions` (mismo valor que
 * `entity_version_id` en database/data/template_versions_mock.php).
 *
 * Versión actual, submitted_at y published_at se derivan de
 * {@see \App\Models\Document} y `entity_versions` / `document_reviews`.
 */

$pack = require __DIR__ . '/programaciones_didacticas_pack.php';

return $pack['documents'];
