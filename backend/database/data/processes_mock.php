<?php

/**
 * Mapa de procesos del CEEDCV.
 *
 * Estructura jerárquica:
 *   - Procesos top-level (parent_id = null) — códigos PE0X / PC0X / PS0X
 *   - Subprocesos (parent_id = uuid del proceso padre) — códigos PE0X.0Y / etc.
 *
 * UUIDs deterministas. Convención del último segmento (XX|YY|ZZWW):
 *   XX = sección (00=PE Estratégicos, 10=PC Clave, 20=PS Soporte)
 *   YY = número de proceso (01..99)
 *   ZZ = número de subproceso (00 = top-level, 01..99 = subproceso)
 *   WW = relleno (00)
 *
 * Ejemplos:
 *   PE01      → 33333333-3333-3333-3333-333333000100
 *   PE01.01   → 33333333-3333-3333-3333-333333000101
 *   PC01      → 33333333-3333-3333-3333-333333010100
 *   PS01.05   → 33333333-3333-3333-3333-333333020105
 */

$uid = static fn (string $tail): string => "33333333-3333-3333-3333-333333{$tail}";

$processes = [];

/* ── PE — Procesos Estratégicos ──────────────────────────────────────────── */

$processes[] = ['id' => $uid('000100'), 'code' => 'PE01', 'name' => 'Planificación estratégica',                                  'alias' => 'pe01_planificacion_estrategica',          'parent_id' => null];
$processes[] = ['id' => $uid('000101'), 'code' => 'PE01.01', 'name' => 'Análisis de contexto',                                    'alias' => 'pe01_01_analisis_contexto',               'parent_id' => $uid('000100')];
$processes[] = ['id' => $uid('000102'), 'code' => 'PE01.02', 'name' => 'Planificación y seguimiento de objetivos',                'alias' => 'pe01_02_planificacion_seguimiento_obj',   'parent_id' => $uid('000100')];

$processes[] = ['id' => $uid('000200'), 'code' => 'PE02', 'name' => 'Mejora continua',                                            'alias' => 'pe02_mejora_continua',                    'parent_id' => null];
$processes[] = ['id' => $uid('000201'), 'code' => 'PE02.01', 'name' => 'Gestión documental',                                      'alias' => 'pe02_01_gestion_documental',              'parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000202'), 'code' => 'PE02.02', 'name' => 'Satisfacción de partes interesadas',                      'alias' => 'pe02_02_satisfaccion_partes',             'parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000203'), 'code' => 'PE02.03', 'name' => 'Seguimiento y medición',                                  'alias' => 'pe02_03_seguimiento_medicion',            'parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000204'), 'code' => 'PE02.04', 'name' => 'No conformidades',                                        'alias' => 'pe02_04_no_conformidades',                'parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000205'), 'code' => 'PE02.05', 'name' => 'Auditorías',                                              'alias' => 'pe02_05_auditorias',                      'parent_id' => $uid('000200')];

$processes[] = ['id' => $uid('000300'), 'code' => 'PE03', 'name' => 'Comunicación',                                               'alias' => 'pe03_comunicacion',                       'parent_id' => null];
$processes[] = ['id' => $uid('000301'), 'code' => 'PE03.01', 'name' => 'Comunicación interna',                                    'alias' => 'pe03_01_comunicacion_interna',            'parent_id' => $uid('000300')];
$processes[] = ['id' => $uid('000302'), 'code' => 'PE03.02', 'name' => 'Comunicación externa',                                    'alias' => 'pe03_02_comunicacion_externa',            'parent_id' => $uid('000300')];

$processes[] = ['id' => $uid('000400'), 'code' => 'PE04', 'name' => 'Innovación, internacionalización y responsabilidad social', 'alias' => 'pe04_innovacion_internacional_rs',        'parent_id' => null];

/* ── PC — Procesos Clave ─────────────────────────────────────────────────── */

// PC01 mantiene el UUID legacy (PROG_DID) para compatibilidad con seeders de templates/documents.
$pc01 = '33333333-3333-3333-3333-333333333301';
$processes[] = ['id' => $pc01,           'code' => 'PC01', 'name' => 'Diseño curricular y planificación docente',                'alias' => 'pc01_diseno_curricular',                  'parent_id' => null];
$processes[] = ['id' => $uid('010101'), 'code' => 'PC01.01', 'name' => 'Actividades extraescolares y complementarias',           'alias' => 'pc01_01_actividades_extraescolares',      'parent_id' => $pc01];

$processes[] = ['id' => $uid('010200'), 'code' => 'PC02', 'name' => 'Recursos de aprendizaje',                                    'alias' => 'pc02_recursos_aprendizaje',               'parent_id' => null];
$processes[] = ['id' => $uid('010201'), 'code' => 'PC02.01', 'name' => 'Materiales educativos digitales',                         'alias' => 'pc02_01_materiales_digitales',            'parent_id' => $uid('010200')];
$processes[] = ['id' => $uid('010202'), 'code' => 'PC02.02', 'name' => 'Equipamiento TIC',                                        'alias' => 'pc02_02_equipamiento_tic',                'parent_id' => $uid('010200')];
$processes[] = ['id' => $uid('010203'), 'code' => 'PC02.03', 'name' => 'Aulas virtuales',                                         'alias' => 'pc02_03_aulas_virtuales',                 'parent_id' => $uid('010200')];

$processes[] = ['id' => $uid('010300'), 'code' => 'PC03', 'name' => 'Orientación educativa y profesional',                        'alias' => 'pc03_orientacion_educativa',              'parent_id' => null];
$processes[] = ['id' => $uid('010301'), 'code' => 'PC03.01', 'name' => 'Servicio de información y orientación profesional',       'alias' => 'pc03_01_servicio_orientacion',            'parent_id' => $uid('010300')];

$processes[] = ['id' => $uid('010400'), 'code' => 'PC04', 'name' => 'Inclusión educativa',                                        'alias' => 'pc04_inclusion_educativa',                'parent_id' => null];
$processes[] = ['id' => $uid('010401'), 'code' => 'PC04.01', 'name' => 'Atención domiciliaria',                                   'alias' => 'pc04_01_atencion_domiciliaria',           'parent_id' => $uid('010400')];
$processes[] = ['id' => $uid('010402'), 'code' => 'PC04.02', 'name' => 'Entidades vinculadas',                                    'alias' => 'pc04_02_entidades_vinculadas',            'parent_id' => $uid('010400')];

$processes[] = ['id' => $uid('010500'), 'code' => 'PC05', 'name' => 'Acción tutorial',                                            'alias' => 'pc05_accion_tutorial',                    'parent_id' => null];
$processes[] = ['id' => $uid('010600'), 'code' => 'PC06', 'name' => 'Práctica docente',                                           'alias' => 'pc06_practica_docente',                   'parent_id' => null];
$processes[] = ['id' => $uid('010700'), 'code' => 'PC07', 'name' => 'Evaluación del aprendizaje',                                 'alias' => 'pc07_evaluacion_aprendizaje',             'parent_id' => null];
$processes[] = ['id' => $uid('010800'), 'code' => 'PC08', 'name' => 'Formación en empresas',                                      'alias' => 'pc08_formacion_empresas',                 'parent_id' => null];
$processes[] = ['id' => $uid('010900'), 'code' => 'PC09', 'name' => 'Igualdad y convivencia',                                     'alias' => 'pc09_igualdad_convivencia',               'parent_id' => null];
$processes[] = ['id' => $uid('011000'), 'code' => 'PC10', 'name' => 'Pruebas libres',                                             'alias' => 'pc10_pruebas_libres',                     'parent_id' => null];

/* ── PS — Procesos Soporte ───────────────────────────────────────────────── */

$processes[] = ['id' => $uid('020100'), 'code' => 'PS01', 'name' => 'Gestión administrativa',                                     'alias' => 'ps01_gestion_administrativa',             'parent_id' => null];
$processes[] = ['id' => $uid('020101'), 'code' => 'PS01.01', 'name' => 'Matrícula FPA',                                           'alias' => 'ps01_01_matricula_fpa',                   'parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020102'), 'code' => 'PS01.02', 'name' => 'Matrícula Bachillerato',                                  'alias' => 'ps01_02_matricula_bachillerato',          'parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020103'), 'code' => 'PS01.03', 'name' => 'Matrícula FP',                                            'alias' => 'ps01_03_matricula_fp',                    'parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020104'), 'code' => 'PS01.04', 'name' => 'Certificación, titulación y expedientes',                 'alias' => 'ps01_04_certificacion_titulacion',        'parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020105'), 'code' => 'PS01.05', 'name' => 'Certificados de aprovechamiento FPA',                     'alias' => 'ps01_05_certificados_aprovechamiento_fpa','parent_id' => $uid('020100')];

$processes[] = ['id' => $uid('020200'), 'code' => 'PS02', 'name' => 'Gestión RRHH',                                               'alias' => 'ps02_gestion_rrhh',                       'parent_id' => null];
$processes[] = ['id' => $uid('020201'), 'code' => 'PS02.01', 'name' => 'Acogida del personal',                                    'alias' => 'ps02_01_acogida_personal',                'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020202'), 'code' => 'PS02.02', 'name' => 'Formación',                                               'alias' => 'ps02_02_formacion',                       'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020203'), 'code' => 'PS02.03', 'name' => 'Competencia',                                             'alias' => 'ps02_03_competencia',                     'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020204'), 'code' => 'PS02.04', 'name' => 'Gestión de horarios',                                     'alias' => 'ps02_04_gestion_horarios',                'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020205'), 'code' => 'PS02.05', 'name' => 'Ausencias y guardias',                                    'alias' => 'ps02_05_ausencias_guardias',              'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020206'), 'code' => 'PS02.06', 'name' => 'Alumnado en prácticas',                                   'alias' => 'ps02_06_alumnado_practicas',              'parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020207'), 'code' => 'PS02.07', 'name' => 'Prevención de riesgos laborales',                         'alias' => 'ps02_07_prevencion_riesgos',              'parent_id' => $uid('020200')];

$processes[] = ['id' => $uid('020300'), 'code' => 'PS03', 'name' => 'Gestión de infraestructuras y equipamiento de soporte',     'alias' => 'ps03_infraestructuras_equipamiento',      'parent_id' => null];
$processes[] = ['id' => $uid('020301'), 'code' => 'PS03.01', 'name' => 'Mantenimiento y renovación de equipos',                   'alias' => 'ps03_01_mantenimiento_renovacion',        'parent_id' => $uid('020300')];

$processes[] = ['id' => $uid('020500'), 'code' => 'PS05', 'name' => 'Protección de datos y seguridad de la información',         'alias' => 'ps05_proteccion_datos',                   'parent_id' => null];
$processes[] = ['id' => $uid('020600'), 'code' => 'PS06', 'name' => 'Gestión económica y compras',                                'alias' => 'ps06_gestion_economica_compras',          'parent_id' => null];

return [
    'processes' => array_map(static fn (array $p): array => $p + ['description' => null], $processes),
];
