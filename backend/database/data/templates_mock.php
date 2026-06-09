<?php

declare(strict_types=1);
use App\Models\EntityVersion;

/**
 * Datos mock de plantillas (programaciones didácticas reales del CEEDCV).
 *
 * El pack consumido define 3 plantillas publicadas:
 * - T0: Programación de ciclo formativo (FP) — visibility=study_type, study_type_id=GS
 * - T1: Programación didáctica de módulo (FP) — visibility=study_type, study_type_id=GS
 * - T2: Programación didáctica de asignatura (Bachillerato) — visibility=study_type, study_type_id=NG
 *
 * IDs de jerarquía académica reales de Odoo (ver maya_infra/odoo_db.sql, view v_dms_study_types).
 *
 * El número de versión publicada vive en {@see EntityVersion}; no se incluye aquí.
 */
$pack = require __DIR__.'/programaciones_didacticas_pack.php';

return [
    'templates' => $pack['templates'],
    'template_reviewers' => $pack['template_reviewers'],
    'template_document_reviewers' => $pack['template_document_reviewers'],
];
