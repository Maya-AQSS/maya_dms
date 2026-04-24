<?php

declare(strict_types=1);

/**
 * Plantillas de programación didáctica por módulo + documentos demo (seed programático).
 *
 * Contenido en formato **array de bloques BlockNote** (compatible con BlockContentHtml / editor).
 * Módulos **FP** (ST_FP): plantilla base genérica de programación didáctica (identificación, objetivos,
 * unidades, metodología, evaluación, recursos), reutilizable en cualquier módulo del ciclo.
 *
 * @return array{
 *   templates: list<array<string, mixed>>,
 *   template_blocks: list<array<string, mixed>>,
 *   template_versions: list<array<string, mixed>>,
 *   template_reviewers: list<array<string, mixed>>,
 *   template_document_reviewers: list<array<string, mixed>>,
 *   documents: list<array<string, mixed>>,
 *   document_blocks: list<array<string, mixed>>
 * }
 */

return (static function (): array {
    $uDir = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
    $uSec = '2ead4bf3-574c-41b4-95ca-cac7daed0664';
    $uAud = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';
    $uEsp = 'cf8bb92a-0417-4a4c-918a-08dd3fd69165';
    $uBach = '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f';
    $uFp = '50f503c6-cb63-466c-852d-0b30ae130e98';

    $baseParaProps = [
        'textColor' => 'default',
        'backgroundColor' => 'default',
        'textAlignment' => 'left',
    ];

    $para = static function (string $text) use ($baseParaProps): array {
        return [
            'type' => 'paragraph',
            'props' => $baseParaProps,
            'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
            'children' => [],
        ];
    };

    $heading = static function (int $level, string $text) use ($baseParaProps): array {
        return [
            'type' => 'heading',
            'props' => array_merge($baseParaProps, ['level' => max(1, min(3, $level))]),
            'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
            'children' => [],
        ];
    };

    /** Contenido rico variante según semilla (determinista). */
    $richPack = static function (int $seed, string $short, string $slotLabel) use ($para, $heading): array {
        $v = $seed % 6;
        $suf = " ({$short} · {$slotLabel})";

        return match ($v) {
            0 => [
                $heading(2, 'Introducción y contexto'.$suf),
                $para('Este párrafo resume el enfoque de la programación didáctica para el módulo indicado. Incluye referencias al currículo y al perfil del alumnado.'),
                $heading(3, 'Subapartado: competencias clave'.$suf),
                $para('Las competencias se trabajan de forma integrada a lo largo del curso, con actividades formativas y sumativas.'),
            ],
            1 => [
                $heading(1, 'Título principal del bloque'.$suf),
                $para('Texto expositivo con énfasis pedagógico: secuenciación, recursos y criterios de calidad.'),
                $para('Segundo párrafo con detalle sobre temporalización y posibles incidencias en el aula.'),
            ],
            2 => [
                $heading(2, 'Evaluación formativa'.$suf),
                $para('Instrumentos: observación directa, rúbricas y coevaluación entre el alumnado.'),
                $heading(3, 'Criterios de calificación'.$suf),
                $para('Los criterios se explicitan por resultados de aprendizaje y se alinean con los estándares del ciclo.'),
            ],
            3 => [
                $para('Bloque que comienza con párrafo (sin encabezado previo) para variar el ritmo visual.'.$suf),
                $heading(2, 'Metodología activa'.$suf),
                $para('Aprendizaje basado en proyectos, estudio de casos y puesta en común en gran grupo.'),
            ],
            4 => [
                $heading(2, 'Recursos y materiales'.$suf),
                $para('Aula digital, bibliografía básica y fichas de trabajo descargables desde el aula virtual.'),
                $para('Materiales manipulativos y software específico del módulo cuando aplique.'),
            ],
            default => [
                $heading(2, 'Atención a la diversidad'.$suf),
                $para('Medidas de refuerzo, ampliación y adecuación metodológica. Tutoría y coordinación con orientación.'),
                $heading(3, 'Seguimiento'.$suf),
                $para('Registro de incidencias y acuerdos con el departamento didáctico.'),
            ],
        };
    };

    /**
     * Denominación larga del ciclo GS a partir del study_id del seed (FP).
     */
    $fpCycleLongLabel = static function (string $studyId): string {
        return match (true) {
            str_contains($studyId, 'FP_DAW') => 'Grado Superior en Desarrollo de Aplicaciones Web (DAW)',
            str_contains($studyId, 'FP_ASIR') => 'Grado Superior en Administración de Sistemas Informáticos en Red (ASIR)',
            str_contains($studyId, 'FP_SMR') => 'Grado Superior en Sistemas Microinformáticos en Red (SMR)',
            default => 'Ciclo formativo de grado superior (indicar denominación oficial del título)',
        };
    };

    /**
     * Seis bloques base de programación didáctica **genérica** para cualquier módulo FP.
     * Sustituir en el centro nombres de módulo, códigos oficiales, horas y unidades concretas.
     *
     * @return list<array{db_type: string, title: string, mandatory: bool, sort: int, default_content: list<array<string, mixed>>}>
     */
    $fpGenericProgramacionLayout = static function (int $si, string $short, string $studyId) use ($heading, $para, $fpCycleLongLabel): array {
        $cycle = $fpCycleLongLabel($studyId);
        $ctx = match ($si) {
            0 => 'Borrador en elaboración por Dirección: sustituir el texto genérico por el definitivo del centro.',
            1 => 'Plantilla en circuito de revisión administrativa y académica antes de publicarse.',
            2 => 'Borrador del docente: personalizar contenidos según grupo, aula y recursos disponibles.',
            3 => 'Plantilla publicada (modelo A) lista para generar programaciones en el DMS.',
            default => 'Plantilla publicada (modelo B): misma estructura pedagógica con énfasis en trazabilidad y revisión.',
        };

        $L = static fn (string $dbType, string $title, bool $mand, int $sort, array $bn) => [
            'db_type' => $dbType,
            'title' => $title.' · '.$short,
            'mandatory' => $mand,
            'sort' => $sort,
            'default_content' => $bn,
        ];

        return [
            $L('heading', 'Identificación y contextualización', true, 0, [
                $heading(2, '1. Identificación y contextualización'),
                $para($ctx),
                $para("Ciclo formativo: {$cycle}."),
                $para('Módulo: indicar el nombre oficial del módulo según el currículo autonómico y el catálogo del centro (referencia de plantilla: '.$short.').'),
                $para('Código del módulo: indicar la codificación oficial publicada en la normativa de la comunidad autónoma (ejemplo orientativo: 0612 u otra según título y módulo).'),
                $para('Duración total del módulo: orientativamente entre 160 y 180 horas lectivas, según decreto autonómico y distribución horaria del centro.'),
                $para('Curso de impartición: indicar 1.º, 2.º u otra modalidad (intensiva, semipresencial, etc.) según la programación general del ciclo.'),
            ]),
            $L('heading', 'Objetivos generales y competencias', true, 1, [
                $heading(2, '2. Objetivos generales y competencias'),
                $para('Objetivo general del módulo (redactar al inicio del curso): describir qué debe ser capaz de hacer el alumnado al finalizar, en contextos profesionales o de aprendizaje supervisado, alineado con el perfil de salida del ciclo y con los resultados de aprendizaje del módulo.'),
                $heading(3, 'Competencias y resultados de aprendizaje (ajustar al RD del módulo)'),
                $para('• Analizar y aplicar los fundamentos teórico-prácticos propios del ámbito del módulo en supuestos reales o simulados.'),
                $para('• Diseñar, implementar y documentar soluciones que respondan a requisitos funcionales y no funcionales (calidad, seguridad, accesibilidad).'),
                $para('• Gestionar datos, recursos y entornos de trabajo de forma segura, trazable y colaborativa (control de versiones, copias de seguridad, buenas prácticas).'),
            ]),
            $L('heading', 'Unidades didácticas (cronograma sugerido)', true, 2, [
                $heading(2, '3. Unidades didácticas (cronograma sugerido)'),
                $para('La secuencia, los títulos y la carga horaria son orientativos: el departamento las adaptará al calendario escolar, al proyecto común del ciclo y a la realidad del alumnado.'),
                $heading(3, 'UT 1 — Puesta en marcha y entorno de trabajo'),
                $para('Contenidos clave: normativa del aula o taller, estándares básicos, entorno de desarrollo o laboratorio y revisión de prerrequisitos. Duración orientativa: 15–20 h.'),
                $heading(3, 'UT 2 — Fundamentos técnicos del ámbito del módulo'),
                $para('Núcleo conceptual y habilidades instrumentales que sustentan el resto del curso (sintaxis, modelos, protocolos o herramientas base según el módulo). Duración orientativa: 18–25 h.'),
                $heading(3, 'UT 3 — Diseño e implementación de soluciones'),
                $para('Desarrollo de casos y mini-proyectos que integren los contenidos centrales del módulo. Duración orientativa: 28–38 h.'),
                $heading(3, 'UT 4 — Persistencia, datos o sistemas (según perfil del módulo)'),
                $para('Gestión de información persistente, configuración de servicios o tratamiento de datos de acuerdo con el enfoque del módulo. Duración orientativa: 22–35 h.'),
                $heading(3, 'UT 5 — Calidad, pruebas y buenas prácticas'),
                $para('Pruebas, revisión de código o procedimientos, documentación técnica y criterios de calidad. Duración orientativa: 20–28 h.'),
                $heading(3, 'UT 6 — Integración, despliegue o comunicaciones'),
                $para('Integración con otros componentes del sistema, despliegue controlado, APIs o comunicaciones según corresponda al módulo. Duración orientativa: 22–32 h.'),
                $heading(3, 'UT 7 — Proyecto integrador, seguridad y cierre'),
                $para('Proyecto que articula contenidos previos; repaso de seguridad, sesiones, permisos o aspectos críticos del ámbito. Duración orientativa: 25–35 h.'),
            ]),
            $L('paragraph', 'Metodología', true, 3, [
                $heading(2, '4. Metodología'),
                $para('Se recomienda un enfoque práctico y orientado a proyectos (ABP u otras metodologías activas acordadas en el departamento).'),
                $para('• Explicación teórica breve apoyada en ejemplos resueltos o demostraciones guiadas en el aula o laboratorio.'),
                $para('• Retos o tareas cortas frecuentes para fijar procedimientos y criterios de calidad.'),
                $para('• Proyecto final o integrador que recoja competencias de varias unidades y favorezca el trabajo colaborativo y la autonomía.'),
            ]),
            $L('paragraph', 'Evaluación', false, 4, [
                $heading(2, '5. Evaluación'),
                $para('Evaluación continua y alineada con los resultados de aprendizaje del módulo. Las ponderaciones son orientativas y deben consensuarse en el departamento y publicarse según normativa del centro.'),
                $para('• Pruebas prácticas o escritas con enfoque en resolución de problemas: orientativamente 40 % (ajustar).'),
                $para('• Proyectos, prácticas de unidad o entregas evaluables (calidad, seguridad, funcionalidad): orientativamente 50 % (ajustar).'),
                $para('• Actitud, participación y hábitos de trabajo (repositorio, plazos, colaboración): orientativamente 10 % (ajustar).'),
                $para('Nota importante: consignar en esta plantilla la norma del centro sobre superación de unidades o notas mínimas para la evaluación global del módulo (ej. exigencia de mínimo en cada unidad).'),
            ]),
            $L('paragraph', 'Recursos necesarios', false, 5, [
                $heading(2, '6. Recursos necesarios'),
                $para('Software: entorno de desarrollo o de prácticas acorde con el módulo, control de versiones, herramientas de pruebas o monitorización, y gestor de bases de datos o equivalente si aplica al ciclo.'),
                $para('Hardware: equipos del aula o portátiles del alumnado con capacidad suficiente para los laboratorios previstos (indicar requisitos mínimos del centro).'),
                $para('Plataforma: aula virtual (Moodle, Google Classroom u otra) para materiales, entregas y comunicación con el alumnado.'),
            ]),
        ];
    };

    $statePool = ['locked', 'editable', 'modifiable', 'optional'];
    $pickState = static function (int $mi, int $si, int $bi) use ($statePool): string {
        return $statePool[($mi * 19 + $si * 5 + $bi * 11) % 4];
    };

    $modules = [
        ['module_id' => 'M_MAT_1', 'study_id' => 'S_ESPA', 'study_type_id' => 'ST_ESPA', 'teacher' => $uEsp, 'short' => 'MAT1'],
        ['module_id' => 'M_ENG_1', 'study_id' => 'S_ESPA', 'study_type_id' => 'ST_ESPA', 'teacher' => $uEsp, 'short' => 'ING1'],
        ['module_id' => 'M_LEN_2', 'study_id' => 'S_ESPA', 'study_type_id' => 'ST_ESPA', 'teacher' => $uEsp, 'short' => 'LEN2'],
        ['module_id' => 'M_FIS_1C', 'study_id' => 'S_BACH_1_C', 'study_type_id' => 'ST_BACH', 'teacher' => $uBach, 'short' => 'FIS1C'],
        ['module_id' => 'M_BIO_2C', 'study_id' => 'S_BACH_2_C', 'study_type_id' => 'ST_BACH', 'teacher' => $uBach, 'short' => 'BIO2C'],
        ['module_id' => 'M_DAW_DWECL', 'study_id' => 'S_FP_DAW', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'DWECL'],
        ['module_id' => 'M_DAW_DWES', 'study_id' => 'S_FP_DAW', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'DWES'],
        ['module_id' => 'M_DAW_DIW', 'study_id' => 'S_FP_DAW', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'DIW'],
        ['module_id' => 'M_ASIR_SRI', 'study_id' => 'S_FP_ASIR', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'SRI'],
        ['module_id' => 'M_ASIR_SAD', 'study_id' => 'S_FP_ASIR', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'SAD'],
        ['module_id' => 'M_SMR_MME', 'study_id' => 'S_FP_SMR_1', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'MME'],
        ['module_id' => 'M_SMR_PAR', 'study_id' => 'S_FP_SMR_2', 'study_type_id' => 'ST_FP', 'teacher' => $uFp, 'short' => 'PAR'],
    ];

    /** @return list<array{title: string, mandatory: bool, sort: int, db_type: string}> */
    $slotLayout = static function (int $si, string $short) use ($richPack, $para, $heading): array {
        $L = static fn (string $dbType, string $title, bool $mand, int $sort, array $bn) => [
            'db_type' => $dbType,
            'title' => $title.' · '.$short,
            'mandatory' => $mand,
            'sort' => $sort,
            'default_content' => $bn,
        ];

        return match ($si) {
            0 => [
                $L('heading', 'Marco normativo', true, 0, [$heading(2, 'Marco normativo y referencias curriculares · '.$short), $para('Este bloque resume la normativa autonómica y estatal aplicable al módulo. Texto de ejemplo para vista previa.')]),
                $L('paragraph', 'Objetivos didácticos', true, 1, [$para('Objetivos generales y específicos alineados con competencias y criterios de evaluación del curso.')]),
                $L('heading', 'Competencias específicas', false, 2, [$heading(3, 'Competencias del módulo · '.$short), $para('Desglose por competencia con indicadores observables en el aula.')]),
                $L('paragraph', 'Temporalización orientativa', false, 3, [$para('Distribución orientativa por trimestres con hitos de evaluación y posibles ajustes según calendario del centro.')]),
                $L('paragraph', 'Coordinación departamental', false, 4, [$para('Acuerdos con el departamento, materiales comunes y rúbricas compartidas entre el claustro.')]),
            ],
            1 => [
                $L('heading', 'Estado de la revisión', true, 0, [$heading(2, 'Plantilla en revisión · '.$short), $para('Este documento normativo está pendiente de validación por Dirección, Auditoría y docencia antes de publicarse.')]),
                $L('paragraph', 'Observaciones de Secretaría', true, 1, [$para('Observaciones administrativas y cumplimiento de plazos de calidad interna.')]),
                $L('paragraph', 'Checklist de revisión', false, 2, [$para('1) Coherencia curricular  2) Criterios evaluables  3) Accesibilidad de recursos  4) Adecuación al alumnado.')]),
            ],
            2 => [
                $L('heading', 'Propuesta docente', true, 0, [$heading(2, 'Borrador del docente · '.$short), $para('Versión inicial elaborada por el profesorado del ámbito. Puede sufrir cambios antes de consensuar con el departamento.')]),
                $L('paragraph', 'Secuencia de unidades', true, 1, [$para('Unidad 1: diagnóstico inicial. Unidad 2: núcleo conceptual. Unidad 3: síntesis y evaluación integradora.')]),
                $L('heading', 'Actividades destacadas', false, 2, [$heading(3, 'Actividades · '.$short), $para('Talleres, prácticas de laboratorio o aula informática según el perfil del módulo.')]),
                $L('paragraph', 'Materiales y enlaces', false, 3, [$para('Enlaces a LMS, repositorio de fichas y vídeos cortos de apoyo (placeholders de ejemplo).')]),
            ],
            3 => [
                $L('heading', 'Programación publicada (modelo A)', true, 0, [$heading(1, 'Programación didáctica — modelo A · '.$short), $para('Versión oficial publicada por Dirección. Sirve como base para generar documentos de programación en el DMS.')]),
                $L('paragraph', 'Ejes y resultados', true, 1, [$para('Ejes competenciales y resultados de aprendizaje con formulación observable para el alumnado.')]),
                $L('heading', 'Instrumentos de evaluación', false, 2, [$heading(2, 'Evaluación · '.$short), $para('Pruebas escritas, prácticas, portafolios y observación sistemática.')]),
                $L('paragraph', 'Bibliografía mínima', false, 3, [$para('Referencias básicas y ampliación recomendada para el alumnado de excelencia académica.')]),
            ],
            default => [
                $L('heading', 'Programación publicada (modelo B)', true, 0, [$heading(1, 'Programación didáctica — modelo B · '.$short), $para('Variante publicada por Secretaría con énfasis en calidad administrativa y trazabilidad.')]),
                $L('paragraph', 'Unidades didácticas', true, 1, $richPack(4, $short, 'UD')),
                $L('paragraph', 'Criterios de calificación', false, 2, [$para('Ponderación orientativa: 40% continua, 30% práctica, 30% prueba final (ajustable por centro).')]),
            ],
        };
    };

    $templates = [];
    $blocks = [];
    $versions = [];
    $reviewers = [];
    $docReviewers = [];
    $publishedIndex = [];

    $tid = 340;
    $blockHex = 0x5c0000;
    $vidHex = 0x7a0000;
    $ridHex = 0x1b0;

    foreach ($modules as $mi => $mod) {
        $m = $mod['module_id'];
        $studyId = $mod['study_id'];
        $stype = $mod['study_type_id'];
        $teach = $mod['teacher'];
        $short = $mod['short'];

        $slots = [
            [
                'creator' => $uDir,
                'status' => 'draft',
                'name' => "Programación didáctica — {$short} (borrador, Dirección)",
                'desc' => 'Borrador creado por Dirección; visibilidad por módulo.',
                'review_stages' => 1,
                'review_mode' => 'sequential',
            ],
            [
                'creator' => $uSec,
                'status' => 'in_review',
                'name' => "Programación didáctica — {$short} (en revisión, Secretaría)",
                'desc' => 'En revisión: Dirección → Auditoría → docente de ámbito (orden secuencial).',
                'review_stages' => 3,
                'review_mode' => 'sequential',
            ],
            [
                'creator' => $teach,
                'status' => 'draft',
                'name' => "Programación didáctica — {$short} (borrador, docente)",
                'desc' => 'Borrador del docente del ámbito del módulo.',
                'review_stages' => 1,
                'review_mode' => 'parallel',
            ],
            [
                'creator' => $uDir,
                'status' => 'published',
                'name' => "Programación didáctica — {$short} (publicada A)",
                'desc' => 'Plantilla publicada para generar programaciones (opción A).',
                'review_stages' => 1,
                'review_mode' => 'parallel',
            ],
            [
                'creator' => $uSec,
                'status' => 'published',
                'name' => "Programación didáctica — {$short} (publicada B)",
                'desc' => 'Plantilla publicada alternativa (opción B).',
                'review_stages' => 1,
                'review_mode' => 'parallel',
            ],
        ];

        foreach ($slots as $si => $slot) {
            $tUuid = sprintf('33333333-3333-3333-3333-%012d', $tid++);

            $templates[] = [
                'id' => $tUuid,
                'name' => $slot['name'],
                'description' => $slot['desc'],
                'visibility_level' => 'module',
                'delivery_deadline' => null,
                'study_id' => $studyId,
                'study_type_id' => $stype,
                'module_id' => $m,
                'team_id' => null,
                'created_by' => $slot['creator'],
                'status' => $slot['status'],
                'version' => 1,
                'review_stages' => $slot['review_stages'],
                'review_mode' => $slot['review_mode'],
            ];

            $layoutRows = $stype === 'ST_FP'
                ? $fpGenericProgramacionLayout($si, $short, $studyId)
                : $slotLayout($si, $short);
            $snapshot = [];
            foreach ($layoutRows as $bi => $row) {
                $blockUuid = sprintf('55555555-5555-5555-5555-%012x', $blockHex++);
                $state = $pickState($mi, $si, $bi);
                if (($row['mandatory'] ?? false) && $state === 'optional') {
                    $state = 'editable';
                }
                $seed = $mi * 31 + $si * 13 + $bi;
                $content = $row['default_content'];
                if ($content === []) {
                    $content = $richPack($seed, $short, 'relleno');
                }

                $blocks[] = [
                    'id' => $blockUuid,
                    'template_id' => $tUuid,
                    'title' => $row['title'],
                    'default_content' => $content,
                    'block_state' => $state,
                    'sort_order' => $row['sort'],
                ];
                $snapshot[] = [
                    'id' => $blockUuid,
                    'title' => $row['title'],
                    'default_content' => $content,
                    'block_state' => $state,
                    'sort_order' => $row['sort'],
                ];
            }

            if ($slot['status'] === 'published') {
                $vUuid = sprintf('66666666-6666-6666-6666-%012x', $vidHex++);
                $versions[] = [
                    'id' => $vUuid,
                    'template_id' => $tUuid,
                    'version_number' => 1,
                    'blocks_snapshot' => $snapshot,
                    'changelog' => 'Publicación seed — '.$slot['name'],
                    'published_by' => $slot['creator'],
                    'published_at' => null,
                ];
                $publishedIndex[] = [
                    'template_id' => $tUuid,
                    'template_version_id' => $vUuid,
                    'study_type_id' => $stype,
                    'study_id' => $studyId,
                    'module_id' => $m,
                    'short' => $short,
                ];
            }

            if ($slot['status'] === 'in_review') {
                foreach ([[$uDir, 1], [$uAud, 2], [$teach, 3]] as [$uid, $stg]) {
                    $reviewers[] = [
                        'id' => sprintf('44444444-4444-4444-4444-%012x', $ridHex++),
                        'template_id' => $tUuid,
                        'user_id' => $uid,
                        'stage' => $stg,
                    ];
                }
            }

            if ($slot['status'] === 'published') {
                $creatorId = $slot['creator'];
                foreach ([$uDir, $uSec, $uAud, $teach] as $uid) {
                    if ($uid === $creatorId) {
                        continue;
                    }
                    $reviewers[] = [
                        'id' => sprintf('44444444-4444-4444-4444-%012x', $ridHex++),
                        'template_id' => $tUuid,
                        'user_id' => $uid,
                        'stage' => 1,
                    ];
                }
                $docBase = ($si === 3)
                    ? [$uDir, $uSec, $uAud]
                    : [$uSec, $uAud, $teach];
                foreach ($docBase as $uid) {
                    if ($uid === $creatorId) {
                        continue;
                    }
                    $docReviewers[] = ['template_id' => $tUuid, 'user_id' => $uid];
                }
            }
        }
    }

    $demoDocuments = [];
    $demoDocBlocks = [];

    /**
     * Índices en $publishedIndex: por cada módulo hay dos entradas consecutivas (plantilla publicada A y B).
     * Se asignan dos documentos por rol; los docentes usan plantillas de su etapa/módulo.
     *
     * @var list<array{0: string, 1: string, 2: list<int>}>
     */
    $ownerDocPlans = [
        [$uDir, 'Dirección', [0, 1]],
        [$uSec, 'Secretaría', [2, 3]],
        [$uAud, 'Auditoría', [4, 5]],
        [$uEsp, 'ESPA', [0, 2]],
        [$uBach, 'Bachillerato', [6, 8]],
        [$uFp, 'FP', [10, 11]],
    ];

    $docIdNum = 1700;
    $dblkNum = 9100;

    foreach ($ownerDocPlans as [$ownerId, $label, $pubIndices]) {
        foreach ($pubIndices as $k => $pubIdx) {
            $pub = $publishedIndex[$pubIdx] ?? null;
            if ($pub === null) {
                continue;
            }
            $docUuid = sprintf('77777777-7777-7777-7777-%012d', $docIdNum++);
            $short = $pub['short'];
            $demoDocuments[] = [
                'id' => $docUuid,
                'template_id' => $pub['template_id'],
                'template_version_id' => $pub['template_version_id'],
                'title' => 'Programación demo — '.$label.' · '.$short.' #'.($k + 1),
                'study_type_id' => $pub['study_type_id'],
                'study_id' => $pub['study_id'],
                'module_id' => $pub['module_id'],
                'created_by' => $ownerId,
                'owner_id' => $ownerId,
                'status' => 'draft',
                'current_version' => 1,
                'submitted_at' => null,
                'published_at' => null,
            ];

            foreach ($blocks as $b) {
                if ($b['template_id'] !== $pub['template_id']) {
                    continue;
                }
                $blkUuid = sprintf('88888888-8888-8888-8888-%012d', $dblkNum++);
                $base = $b['default_content'];
                $extra = $para('Contenido ampliado en el documento demo (usuario '.$label.', copia n.º '.($k + 1).'). Puedes editarlo si el bloque no está bloqueado.');
                $merged = is_array($base) ? [...$base, $extra] : [$extra];

                $demoDocBlocks[] = [
                    'id' => $blkUuid,
                    'document_id' => $docUuid,
                    'template_block_id' => $b['id'],
                    'content' => $merged,
                    'is_filled' => true,
                    'last_edited_by' => $ownerId,
                    'locked_by' => null,
                    'locked_at' => null,
                    'sort_order' => $b['sort_order'],
                ];
            }
        }
    }

    return [
        'templates' => $templates,
        'template_blocks' => $blocks,
        'template_versions' => $versions,
        'template_reviewers' => $reviewers,
        'template_document_reviewers' => $docReviewers,
        'documents' => $demoDocuments,
        'document_blocks' => $demoDocBlocks,
    ];
})();
