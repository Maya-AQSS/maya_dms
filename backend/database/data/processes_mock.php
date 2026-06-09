<?php

declare(strict_types=1);

/**
 * Mapa de procesos del CEEDCV.
 *
 * Estructura jerárquica:
 *   - Procesos top-level (process_parent_id = null) — códigos PE0X / PC0X / PS0X
 *   - Subprocesos (process_parent_id = uuid del proceso padre) — códigos PE0X.0Y / etc.
 *
 * UUIDs deterministas. Convención del último segmento (XX|YY|ZZWW):
 *   XX = sección (00=PE Estratégicos, 10=PC Clave, 20=PS Soporte)
 *   YY = número de proceso (01..99)
 *   ZZ = número de subproceso (00 = top-level, 01..99 = subproceso)
 *   WW = relleno (00)
 *
 * `alias` es el texto user-facing que se muestra en el sidebar y otros
 * listados compactos. Máximo 25 caracteres. `name` es el nombre completo
 * (uso administrativo). `icon` es un slug de icono (`processIcons.tsx` en
 * el frontend lo resuelve a un SVG). `color` es hex `#RRGGBB` único por
 * proceso — asignado con HSL distribuido para garantizar unicidad
 * cromática dentro de cada categoría (PE=purples, PC=teals/blues, PS=warm).
 */
$uid = static fn (string $tail): string => "33333333-3333-3333-3333-333333{$tail}";

/** Convierte HSL → hex `#RRGGBB`. */
$hsl = static function (int $h, int $s, int $l): string {
    $s /= 100;
    $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    [$r, $g, $b] = match (true) {
        $h < 60 => [$c, $x, 0],
        $h < 120 => [$x, $c, 0],
        $h < 180 => [0, $c, $x],
        $h < 240 => [0, $x, $c],
        $h < 300 => [$x, 0, $c],
        default => [$c, 0, $x],
    };

    return sprintf('#%02X%02X%02X',
        (int) round(($r + $m) * 255),
        (int) round(($g + $m) * 255),
        (int) round(($b + $m) * 255),
    );
};

$processes = [];

/* ── PE — Procesos Estratégicos (purples/magentas, hue 270-330) ──────────── */

$processes[] = ['id' => $uid('000100'), 'code' => 'PE01',    'name' => 'Planificación estratégica',                                  'alias' => 'Planif. estratégica',     'icon' => 'target',           'color' => $hsl(270, 60, 50), 'process_parent_id' => null];
$processes[] = ['id' => $uid('000101'), 'code' => 'PE01.01', 'name' => 'Análisis de contexto',                                       'alias' => 'Análisis de contexto',    'icon' => 'search',           'color' => $hsl(274, 55, 55), 'process_parent_id' => $uid('000100')];
$processes[] = ['id' => $uid('000102'), 'code' => 'PE01.02', 'name' => 'Planificación y seguimiento de objetivos',                  'alias' => 'Seguim. objetivos',       'icon' => 'flag',             'color' => $hsl(278, 55, 55), 'process_parent_id' => $uid('000100')];

$processes[] = ['id' => $uid('000200'), 'code' => 'PE02',    'name' => 'Mejora continua',                                            'alias' => 'Mejora continua',         'icon' => 'trending-up',      'color' => $hsl(284, 60, 50), 'process_parent_id' => null];
$processes[] = ['id' => $uid('000201'), 'code' => 'PE02.01', 'name' => 'Gestión documental',                                         'alias' => 'Gestión documental',      'icon' => 'file-text',        'color' => $hsl(288, 55, 55), 'process_parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000202'), 'code' => 'PE02.02', 'name' => 'Satisfacción de partes interesadas',                         'alias' => 'Satisfacción partes',     'icon' => 'smile',            'color' => $hsl(292, 55, 55), 'process_parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000203'), 'code' => 'PE02.03', 'name' => 'Seguimiento y medición',                                     'alias' => 'Seguim. y medición',      'icon' => 'activity',         'color' => $hsl(296, 55, 55), 'process_parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000204'), 'code' => 'PE02.04', 'name' => 'No conformidades',                                           'alias' => 'No conformidades',        'icon' => 'alert-triangle',   'color' => $hsl(300, 55, 55), 'process_parent_id' => $uid('000200')];
$processes[] = ['id' => $uid('000205'), 'code' => 'PE02.05', 'name' => 'Auditorías',                                                 'alias' => 'Auditorías',              'icon' => 'check-circle',     'color' => $hsl(304, 55, 55), 'process_parent_id' => $uid('000200')];

$processes[] = ['id' => $uid('000300'), 'code' => 'PE03',    'name' => 'Comunicación',                                               'alias' => 'Comunicación',            'icon' => 'message-square',   'color' => $hsl(310, 60, 50), 'process_parent_id' => null];
$processes[] = ['id' => $uid('000301'), 'code' => 'PE03.01', 'name' => 'Comunicación interna',                                       'alias' => 'Comun. interna',          'icon' => 'inbox',            'color' => $hsl(314, 55, 55), 'process_parent_id' => $uid('000300')];
$processes[] = ['id' => $uid('000302'), 'code' => 'PE03.02', 'name' => 'Comunicación externa',                                       'alias' => 'Comun. externa',          'icon' => 'send',             'color' => $hsl(318, 55, 55), 'process_parent_id' => $uid('000300')];

$processes[] = ['id' => $uid('000400'), 'code' => 'PE04',    'name' => 'Innovación, internacionalización y responsabilidad social', 'alias' => 'Innov. y RS',             'icon' => 'lightbulb',        'color' => $hsl(326, 60, 50), 'process_parent_id' => null];

/* ── PC — Procesos Clave (teals/blues/indigos, hue 170-260) ──────────────── */

// PC01 mantiene el UUID legacy (PROG_DID) para compatibilidad con seeders de templates/documents.
$pc01 = '33333333-3333-3333-3333-333333333301';
$processes[] = ['id' => $pc01,           'code' => 'PC01',    'name' => 'Diseño curricular y planificación docente',                'alias' => 'Diseño curricular',       'icon' => 'book-open',        'color' => $hsl(176, 60, 42), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010101'), 'code' => 'PC01.01', 'name' => 'Actividades extraescolares y complementarias',              'alias' => 'Activ. extraescol.',      'icon' => 'calendar',         'color' => $hsl(180, 55, 45), 'process_parent_id' => $pc01];

$processes[] = ['id' => $uid('010200'), 'code' => 'PC02',    'name' => 'Recursos de aprendizaje',                                   'alias' => 'Recursos aprendizaje',    'icon' => 'library',          'color' => $hsl(188, 60, 42), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010201'), 'code' => 'PC02.01', 'name' => 'Materiales educativos digitales',                           'alias' => 'Materiales digitales',    'icon' => 'monitor',          'color' => $hsl(192, 55, 47), 'process_parent_id' => $uid('010200')];
$processes[] = ['id' => $uid('010202'), 'code' => 'PC02.02', 'name' => 'Equipamiento TIC',                                          'alias' => 'Equipamiento TIC',        'icon' => 'cpu',              'color' => $hsl(196, 55, 47), 'process_parent_id' => $uid('010200')];
$processes[] = ['id' => $uid('010203'), 'code' => 'PC02.03', 'name' => 'Aulas virtuales',                                           'alias' => 'Aulas virtuales',         'icon' => 'video',            'color' => $hsl(200, 55, 47), 'process_parent_id' => $uid('010200')];

$processes[] = ['id' => $uid('010300'), 'code' => 'PC03',    'name' => 'Orientación educativa y profesional',                       'alias' => 'Orient. educativa',       'icon' => 'compass',          'color' => $hsl(206, 60, 45), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010301'), 'code' => 'PC03.01', 'name' => 'Servicio de información y orientación profesional',         'alias' => 'Servicio orientación',    'icon' => 'help-circle',      'color' => $hsl(210, 55, 50), 'process_parent_id' => $uid('010300')];

$processes[] = ['id' => $uid('010400'), 'code' => 'PC04',    'name' => 'Inclusión educativa',                                       'alias' => 'Inclusión educativa',     'icon' => 'users',            'color' => $hsl(216, 60, 50), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010401'), 'code' => 'PC04.01', 'name' => 'Atención domiciliaria',                                     'alias' => 'Atención domic.',         'icon' => 'home',             'color' => $hsl(220, 55, 55), 'process_parent_id' => $uid('010400')];
$processes[] = ['id' => $uid('010402'), 'code' => 'PC04.02', 'name' => 'Entidades vinculadas',                                      'alias' => 'Entidades vinculadas',    'icon' => 'link',             'color' => $hsl(224, 55, 55), 'process_parent_id' => $uid('010400')];

$processes[] = ['id' => $uid('010500'), 'code' => 'PC05',    'name' => 'Acción tutorial',                                           'alias' => 'Acción tutorial',         'icon' => 'user-check',       'color' => $hsl(230, 60, 52), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010600'), 'code' => 'PC06',    'name' => 'Práctica docente',                                          'alias' => 'Práctica docente',        'icon' => 'presentation',     'color' => $hsl(236, 60, 52), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010700'), 'code' => 'PC07',    'name' => 'Evaluación del aprendizaje',                                'alias' => 'Evaluación aprend.',      'icon' => 'clipboard-check',  'color' => $hsl(242, 60, 55), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010800'), 'code' => 'PC08',    'name' => 'Formación en empresas',                                     'alias' => 'Formación empresas',      'icon' => 'briefcase',        'color' => $hsl(248, 60, 55), 'process_parent_id' => null];
$processes[] = ['id' => $uid('010900'), 'code' => 'PC09',    'name' => 'Igualdad y convivencia',                                    'alias' => 'Igualdad/convivencia',    'icon' => 'heart',            'color' => $hsl(254, 60, 55), 'process_parent_id' => null];
$processes[] = ['id' => $uid('011000'), 'code' => 'PC10',    'name' => 'Pruebas libres',                                            'alias' => 'Pruebas libres',          'icon' => 'edit-3',           'color' => $hsl(260, 60, 55), 'process_parent_id' => null];

/* ── PS — Procesos Soporte (warm: orange/amber/red-soft, hue 0-60 + 330) ── */

$processes[] = ['id' => $uid('020100'), 'code' => 'PS01',    'name' => 'Gestión administrativa',                                    'alias' => 'Gestión administ.',       'icon' => 'folder',           'color' => $hsl(28, 65, 50), 'process_parent_id' => null];
$processes[] = ['id' => $uid('020101'), 'code' => 'PS01.01', 'name' => 'Matrícula FPA',                                             'alias' => 'Matrícula FPA',           'icon' => 'user-plus',        'color' => $hsl(32, 60, 55), 'process_parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020102'), 'code' => 'PS01.02', 'name' => 'Matrícula Bachillerato',                                    'alias' => 'Matrícula Bachiller.',    'icon' => 'user-plus',        'color' => $hsl(36, 60, 55), 'process_parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020103'), 'code' => 'PS01.03', 'name' => 'Matrícula FP',                                              'alias' => 'Matrícula FP',            'icon' => 'user-plus',        'color' => $hsl(40, 60, 55), 'process_parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020104'), 'code' => 'PS01.04', 'name' => 'Certificación, titulación y expedientes',                   'alias' => 'Cert. y títulos',         'icon' => 'award',            'color' => $hsl(44, 60, 50), 'process_parent_id' => $uid('020100')];
$processes[] = ['id' => $uid('020105'), 'code' => 'PS01.05', 'name' => 'Certificados de aprovechamiento FPA',                       'alias' => 'Cert. aprov. FPA',        'icon' => 'award',            'color' => $hsl(48, 60, 50), 'process_parent_id' => $uid('020100')];

$processes[] = ['id' => $uid('020200'), 'code' => 'PS02',    'name' => 'Gestión RRHH',                                              'alias' => 'Gestión RRHH',            'icon' => 'users-2',          'color' => $hsl(14, 65, 52), 'process_parent_id' => null];
$processes[] = ['id' => $uid('020201'), 'code' => 'PS02.01', 'name' => 'Acogida del personal',                                      'alias' => 'Acogida del personal',    'icon' => 'user-plus',        'color' => $hsl(10, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020202'), 'code' => 'PS02.02', 'name' => 'Formación',                                                 'alias' => 'Formación',               'icon' => 'graduation-cap',   'color' => $hsl(6, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020203'), 'code' => 'PS02.03', 'name' => 'Competencia',                                               'alias' => 'Competencia',             'icon' => 'star',             'color' => $hsl(2, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020204'), 'code' => 'PS02.04', 'name' => 'Gestión de horarios',                                       'alias' => 'Gestión de horarios',     'icon' => 'clock',            'color' => $hsl(358, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020205'), 'code' => 'PS02.05', 'name' => 'Ausencias y guardias',                                      'alias' => 'Ausencias y guardias',    'icon' => 'user-x',           'color' => $hsl(354, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020206'), 'code' => 'PS02.06', 'name' => 'Alumnado en prácticas',                                     'alias' => 'Alumnado prácticas',      'icon' => 'briefcase',        'color' => $hsl(350, 60, 55), 'process_parent_id' => $uid('020200')];
$processes[] = ['id' => $uid('020207'), 'code' => 'PS02.07', 'name' => 'Prevención de riesgos laborales',                           'alias' => 'Prevención riesgos',      'icon' => 'shield',           'color' => $hsl(346, 60, 50), 'process_parent_id' => $uid('020200')];

$processes[] = ['id' => $uid('020300'), 'code' => 'PS03',    'name' => 'Gestión de infraestructuras y equipamiento de soporte',     'alias' => 'Infraestructuras',        'icon' => 'settings',         'color' => $hsl(340, 60, 48), 'process_parent_id' => null];
$processes[] = ['id' => $uid('020301'), 'code' => 'PS03.01', 'name' => 'Mantenimiento y renovación de equipos',                     'alias' => 'Mant. de equipos',        'icon' => 'wrench',           'color' => $hsl(336, 55, 55), 'process_parent_id' => $uid('020300')];

$processes[] = ['id' => $uid('020500'), 'code' => 'PS05',    'name' => 'Protección de datos y seguridad de la información',         'alias' => 'Protección de datos',     'icon' => 'shield',           'color' => $hsl(54, 65, 48), 'process_parent_id' => null];
$processes[] = ['id' => $uid('020600'), 'code' => 'PS06',    'name' => 'Gestión económica y compras',                               'alias' => 'Gestión económica',       'icon' => 'dollar-sign',      'color' => $hsl(60, 65, 48), 'process_parent_id' => null];

return [
    'processes' => array_map(static fn (array $p): array => $p + ['description' => null], $processes),
];
