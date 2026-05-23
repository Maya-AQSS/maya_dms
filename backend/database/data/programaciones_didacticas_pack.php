<?php

declare(strict_types=1);

/**
 * Programaciones didácticas reales del CEEDCV (curso 2025-26) — seed pack.
 *
 * Reemplaza al pack genérico `programacion_per_module_templates_pack.php` (borrado).
 *
 * Contenidos:
 * - 3 plantillas publicadas:
 *   - T0: Programación de ciclo formativo FP        (visibility=study_type, ST_FP=GS)
 *   - T1: Programación didáctica módulo FP          (visibility=study_type, ST_FP=GS)
 *   - T2: Programación didáctica asignatura Bachillerato (visibility=study_type, ST_BACH=NG)
 *
 * - 5 documentos heredados:
 *   - D0: Ciclo ASIR (study=8, sin module_id)
 *   - D1: DWES        (study=7, module=7_2)
 *   - D2: IPO I       (study=7, module=7_7)
 *   - D3: LAP         (study=15, module=15_8)
 *   - D4: PXSI1       (study=3, module=3_9, study_type=NG)
 *
 * IDs de jerarquía académica reales de Odoo (ver maya_infra/odoo_db.sql).
 * UUIDs deterministas: aa* templates, bb* template_blocks, cc* template_versions,
 *                      eea* entity_versions head, eeb* entity_versions published,
 *                      dd* documents, doc-blocks dd<doc>NNNN-....
 *
 * @return array{
 *   templates: list<array<string, mixed>>,
 *   template_blocks: list<array<string, mixed>>,
 *   template_versions: list<array<string, mixed>>,
 *   entity_versions: list<array<string, mixed>>,
 *   documents: list<array<string, mixed>>,
 *   document_blocks: list<array<string, mixed>>,
 * }
 */

return (static function (): array {
    // --- Usuarios reales (UUIDs estables del seed) ---
    $uDir = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';   // Director / Jefe departamento
    $uSec = '2ead4bf3-574c-41b4-95ca-cac7daed0664';   // Secretaría
    $uFp  = '50f503c6-cb63-466c-852d-0b30ae130e98';   // Docente FP
    $uBach = '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f';  // Docente Bachillerato

    // --- Helpers BlockNote ---
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

    $paraBold = static function (string $text) use ($baseParaProps): array {
        return [
            'type' => 'paragraph',
            'props' => $baseParaProps,
            'content' => [['type' => 'text', 'text' => $text, 'styles' => ['bold' => true]]],
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

    $bullet = static function (string $text) use ($baseParaProps): array {
        return [
            'type' => 'bulletListItem',
            'props' => $baseParaProps,
            'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
            'children' => [],
        ];
    };

    /**
     * Representa una tabla como párrafo único con saltos de línea y pipes.
     * Mismo patrón que `dwes_official_programacion_blocknote.php` — funcional y robusto.
     */
    $tableAsPara = static function (array $headers, array $rows) use ($baseParaProps): array {
        $lines = [implode(' | ', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map('strval', $row));
        }
        return [
            'type' => 'paragraph',
            'props' => $baseParaProps,
            'content' => [['type' => 'text', 'text' => implode("\n", $lines), 'styles' => []]],
            'children' => [],
        ];
    };

    // ============================================================
    // PLANTILLAS (3)
    // ============================================================

    $T0 = 'aa000000-0000-4000-8000-000000000000'; // Ciclo FP
    $T1 = 'aa000001-0000-4000-8000-000000000000'; // Módulo FP
    $T2 = 'aa000002-0000-4000-8000-000000000000'; // Asignatura Bach

    $deadline = '2026-09-15 14:00:00';

    $templates = [
        [
            'id' => $T0,
            'name' => 'Programación de ciclo formativo (FP)',
            'description' => 'Plantilla base para programaciones de ciclo formativo de grado superior (FP). '
                .'Bloques bloqueados con texto común del CEEDCV, modificables con placeholders en MAYÚSCULAS y editables libres por departamento.',
            'visibility_level' => 'study_type',
            'study_type_id' => '2',
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'delivery_deadline' => $deadline,
            'created_by' => $uDir,
            'status' => 'published',
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ],
        [
            'id' => $T1,
            'name' => 'Programación didáctica de módulo (FP)',
            'description' => 'Plantilla para programaciones didácticas de un módulo de un ciclo formativo de grado superior. '
                .'Bloques bloqueados con texto común del CEEDCV, modificables con placeholders en MAYÚSCULAS y editables libres por módulo.',
            'visibility_level' => 'study_type',
            'study_type_id' => '2',
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'delivery_deadline' => $deadline,
            'created_by' => $uDir,
            'status' => 'published',
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ],
        [
            'id' => $T2,
            'name' => 'Programación didáctica de asignatura (Bachillerato)',
            'description' => 'Plantilla para programaciones didácticas de una asignatura de Bachillerato (LOMLOE). '
                .'Bloques bloqueados con texto común del CEEDCV, modificables con placeholders en MAYÚSCULAS y editables libres por asignatura.',
            'visibility_level' => 'study_type',
            'study_type_id' => '3',
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'delivery_deadline' => $deadline,
            'created_by' => $uDir,
            'status' => 'published',
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ],
    ];

    // ============================================================
    // ENTITY VERSIONS — head + published por plantilla
    // ============================================================
    // Modelo nuevo (post-migración 24): cada Template tiene un head_entity_version_id
    // (version_number=0) más versiones publicadas (>=1) referenciadas por Document.

    $EV_T0_HEAD = 'eea00000-0000-4000-8000-000000000000';
    $EV_T1_HEAD = 'eea00000-0000-4000-8000-000000000001';
    $EV_T2_HEAD = 'eea00000-0000-4000-8000-000000000002';

    $EV_T0_PUB = 'eeb00000-0000-4000-8000-000000000000';
    $EV_T1_PUB = 'eeb00000-0000-4000-8000-000000000001';
    $EV_T2_PUB = 'eeb00000-0000-4000-8000-000000000002';

    // ============================================================
    // DOCUMENTS (5) — IDs reales de jerarquía Odoo
    // ============================================================

    $D0 = 'dd000000-0000-4000-8000-000000000000'; // Ciclo ASIR
    $D1 = 'dd000001-0000-4000-8000-000000000000'; // DWES
    $D2 = 'dd000002-0000-4000-8000-000000000000'; // IPO I
    $D3 = 'dd000003-0000-4000-8000-000000000000'; // LAP
    $D4 = 'dd000004-0000-4000-8000-000000000000'; // PXSI1

    $documents = [
        [
            'id' => $D0,
            'template_id' => $T0,
            'template_version_id' => $EV_T0_PUB,
            'title' => 'Programación de ciclo — ASIR (curso 2025-26)',
            'study_type_id' => '2',
            'study_id' => '8',           // ASIR
            'module_id' => null,         // Ciclo completo, sin módulo
            'delivery_deadline' => '2026-09-30 14:00:00',
            'created_by' => $uDir,
            'owner_id' => $uDir,
            'status' => 'published',
        ],
        [
            'id' => $D1,
            'template_id' => $T1,
            'template_version_id' => $EV_T1_PUB,
            'title' => 'DWES — Desarrollo Web en Entorno Servidor (0613) — 2025-26',
            'study_type_id' => '2',
            'study_id' => '7',           // DAW
            'module_id' => '7_2',        // DWES
            'delivery_deadline' => '2026-09-30 14:00:00',
            'created_by' => $uFp,
            'owner_id' => $uFp,
            'status' => 'published',
        ],
        [
            'id' => $D2,
            'template_id' => $T1,
            'template_version_id' => $EV_T1_PUB,
            'title' => "IPO I — Itinerari Personal per a l'Ocupabilitat I (1709) — 2025-26",
            'study_type_id' => '2',
            'study_id' => '7',           // DAW (también está en DAM, asignamos al DAW del archivo)
            'module_id' => '7_7',        // IPO1
            'delivery_deadline' => '2026-09-30 14:00:00',
            'created_by' => $uFp,
            'owner_id' => $uFp,
            'status' => 'published',
        ],
        [
            'id' => $D3,
            'template_id' => $T1,
            'template_version_id' => $EV_T1_PUB,
            'title' => 'LAP — Logística de Aprovisionamiento (0626) — 2025-26',
            'study_type_id' => '2',
            'study_id' => '15',          // TIL
            'module_id' => '15_8',       // LAP
            'delivery_deadline' => '2026-09-30 14:00:00',
            'created_by' => $uFp,
            'owner_id' => $uFp,
            'status' => 'published',
        ],
        [
            'id' => $D4,
            'template_id' => $T2,
            'template_version_id' => $EV_T2_PUB,
            'title' => 'PXSI1 — Programación, Redes y Sistemas Informáticos I (1º Bachillerato) — 2025-26',
            'study_type_id' => '3',
            'study_id' => '3',           // BCT
            'module_id' => '3_9',        // PXSI1
            'delivery_deadline' => '2026-09-30 14:00:00',
            'created_by' => $uBach,
            'owner_id' => $uBach,
            'status' => 'published',
        ],
    ];

    // ============================================================
    // TEMPLATE BLOCKS — los 32 bloques (T0=14, T1=10, T2=8)
    // Generador: $L($tplId, $idx, $title, $state, $contentBN, $desc)
    // ============================================================

    $blocks = [];

    // Mapping explícito templateId → índice (evita extraer carácter en posición ambigua).
    $templateIndexMap = [
        $T0 => 0,
        $T1 => 1,
        $T2 => 2,
    ];

    $L = static function (string $tplId, int $idx, string $title, string $state, array $defaultContent, string $desc = '') use (&$blocks, $templateIndexMap): void {
        $template_index = $templateIndexMap[$tplId] ?? 0;
        $blockId = sprintf('bb%06d-0000-4000-8000-%012d', $template_index, $idx);
        $blocks[] = [
            'id' => $blockId,
            'template_id' => $tplId,
            'title' => $title,
            'default_content' => $defaultContent,
            'description' => $desc !== '' ? $desc
                : "Guía para el revisor: verifica que «{$title}» esté completo, sea coherente con la programación y cumpla la normativa del centro antes de validar.",
            'block_state' => $state,
            'sort_order' => $idx,
        ];
    };

    // -------------------------------------------------------------
    // T0 — Programación de ciclo (14 bloques)
    // -------------------------------------------------------------

    $L($T0, 1, 'Cabecera identificación del ciclo', 'modifiable', [
        $heading(1, 'CICLO FORMATIVO DE **NOMBRE_DEL_CICLO**'),
        $paraBold('Departamento: **NOMBRE_DEPARTAMENTO**'),
        $paraBold('Jefe de departamento: **NOMBRE_JEFE_DEPARTAMENTO**'),
        $para('Sustituye los marcadores en MAYÚSCULAS por los datos reales del ciclo antes de publicar.'),
    ]);

    $L($T0, 2, 'Identificación del título', 'editable', [
        $heading(2, 'Identificación del título'),
        $para('Describir aquí la titulación oficial, la duración total, la familia profesional y el nivel del marco de cualificaciones. Incluir tabla resumen con denominación, nivel, duración, familia profesional y referente CINE.'),
    ]);

    $L($T0, 3, 'Marco normativo', 'locked', [
        $heading(2, 'Marco normativo'),
        $para('Este ciclo formativo está regulado por el Real Decreto que establece el título y la Orden de la Conselleria que establece el currículo. También se aplican las normas generales del sistema educativo (LOE 2/2006, LOMLOE 3/2020), de Formación Profesional (Ley 3/2022, RD 659/2023) y de la Comunitat Valenciana (Decreto 104/2018 de inclusión, Orden 79/2010 de evaluación, Orden 12/2022 de FCT, Orden 30/2022 de semipresencial, etc.).'),
        $para('Las referencias normativas concretas del ciclo y del centro se mantienen en este apartado como texto común del CEEDCV.'),
    ]);

    $L($T0, 4, 'Contextualización (centro y alumnado)', 'locked', [
        $heading(2, 'Contextualización. Características del CEEDCV'),
        $heading(3, 'El centro'),
        $para('El Centro Específico de Educación a Distancia de la Comunidad Valenciana (CEEDCV) es centro de referencia en educación a distancia en la Comunidad Valenciana. Se ubica en el Complejo Educativo La Misericordia (Casa de la Misericordia 34, Valencia), con buena comunicación por autobús, taxi y metro.'),
        $heading(3, 'El alumnado'),
        $para('El alumnado del CEEDCV procede de diversas localidades de la Comunidad Valenciana, aprovechando la modalidad a distancia para compaginar sus estudios con responsabilidades laborales o personales. Es un alumnado diverso en edad, formación previa y experiencia laboral.'),
    ]);

    $L($T0, 5, 'Objetivos del ciclo', 'editable', [
        $heading(2, 'Objetivos del ciclo'),
        $para('Listar aquí los objetivos generales del ciclo recogidos en el Real Decreto del título (artículo 9, Capítulo III "Enseñanzas del ciclo formativo y parámetros básicos de contexto"). Cada objetivo describe lo que el alumnado debe ser capaz de hacer al finalizar el ciclo.'),
    ]);

    $L($T0, 6, 'Competencias (general + profesionales)', 'editable', [
        $heading(2, 'Competencias'),
        $heading(3, 'Competencia general'),
        $para('Indicar la competencia general del título, tomada del artículo 4 del Real Decreto que establece el título.'),
        $heading(3, 'Competencias profesionales, personales y sociales'),
        $para('Listar las competencias profesionales, personales y sociales del título.'),
    ]);

    $L($T0, 7, 'Principios y aspectos metodológicos', 'locked', [
        $heading(2, 'Principios y aspectos metodológicos'),
        $heading(3, 'Metodología general y específica del CEEDCV'),
        $para('Dado el carácter de enseñanza a distancia del ciclo, los principios metodológicos se fundamentan en la utilización de las tecnologías de la información y la comunicación, el uso de los recursos que proporciona internet y la utilización de materiales didácticos específicos para el autoaprendizaje. La plataforma utilizada es Aules.'),
        $heading(3, 'Tutorías'),
        $para('En el CEEDCV existen dos tipos de tutorías: Tutorías Colectivas (TC), donde el profesorado expone los contenidos fundamentales de la unidad, orienta el trabajo y resuelve dudas; y Tutorías Individuales (TI), destinadas a la atención personalizada de cada alumno con reserva de hora.'),
        $para('Es obligatorio que el alumno disponga de una cuenta de Office 365 Educativo facilitada por Consellería para la comunicación en el aula. El acceso al Aula Virtual y a los módulos se proporciona en el momento de la matrícula.'),
        $heading(3, 'Estrategias del departamento'),
        $para('Aprendizaje invertido (flipped classroom): los estudiantes acceden a los contenidos fuera del aula, dedicando el tiempo en clase a actividades prácticas y resolución de dudas.'),
        $para('Aprendizaje basado en retos (RD 659/2023, art. 223): plantear situaciones problemáticas reales con retos abiertos y ambiguos, fomentando trabajo cooperativo y colaborativo.'),
        $para('Aprendizaje basado en proyectos (ABP): proyecto integral combinando contenidos en procesos prácticos adaptados a la digitalización.'),
        $para('Aprendizaje basado en tareas: tareas orientadas a resolver problemas reales, organizadas progresivamente. Mínimo una actividad evaluable por evaluación y módulo.'),
    ]);

    $L($T0, 8, 'Evaluación', 'modifiable', [
        $heading(2, 'Evaluación'),
        $para('La primera evaluación se realizará a distancia y la segunda y las convocatorias ordinaria/extraordinaria serán presenciales en el CEEDCV.'),
        $tableAsPara(
            ['EVALUACIÓN', 'TIPO', 'DESDE', 'HASTA', 'EXAMEN'],
            [
                ['CONTINUA', 'EVALUACIÓN 1', '**FECHA_INICIO_EV1**', '**FECHA_FIN_EV1**', 'Online'],
                ['CONTINUA', 'EVALUACIÓN 2', '**FECHA_INICIO_EV2**', '**FECHA_FIN_EV2**', 'Presencial'],
                ['ORDINARIA', 'FINAL', '**FECHA_INICIO_ORD**', '**FECHA_FIN_ORD**', 'Presencial'],
                ['EXTRAORDINARIA', 'FINAL', '**FECHA_INICIO_EXT**', '**FECHA_FIN_EXT**', 'Presencial'],
            ]
        ),
        $para('Sustituye los marcadores **FECHA_*_*** por las fechas reales del calendario del curso.'),
    ]);

    $L($T0, 9, 'Actividades complementarias y extraescolares', 'optional', [
        $heading(2, 'Actividades complementarias y extraescolares'),
        $para('No se contemplan actividades extraescolares en este ciclo, aunque a lo largo del curso se indicarán al alumnado jornadas (jornadas de empleabilidad, jornadas de talento), cursos y seminarios de interés para su desarrollo profesional.'),
    ]);

    $L($T0, 10, 'Medidas de atención al alumnado con necesidades educativas específicas', 'locked', [
        $heading(2, 'Medidas para la atención del alumnado con necesidades educativas específicas'),
        $para('El DECRETO 104/2018, de 27 de julio, del Consell, desarrolla los principios de equidad e inclusión en el sistema educativo valenciano. La educación inclusiva tiene como propósito dar una respuesta educativa que favorezca el máximo desarrollo de todo el alumnado y elimine todas las formas de exclusión, desigualdad y vulnerabilidad.'),
        $para('El alumnado del CEEDCV presenta una gran diversidad respecto a edad, origen, formación previa, disponibilidad de tiempo y acceso telemático. La acción formativa incluye: tutorías individuales para atención a la diversidad, ejercicios suplementarios y trabajos especiales para casos de falta de adaptación, y vídeos subtitulados y herramientas TIC para alumnado con discapacidades.'),
        $heading(3, 'Medidas de Nivel II'),
        $para('Acceso o presencia (Orden 20/2019, art. 11): grabación de TC, tareas en diferentes formatos, comunicación personal por TC/TI.'),
        $para('Participación (Orden 20/2019, art. 38): espacio virtual «foro cafetería», foros de dudas colaborativos, metodologías activas, asistencia a TC, actividades fuera de horario lectivo.'),
        $para('Aprendizaje (Orden 20/2019, art. 14): ficha tutorial, material de refuerzo y autoformativo, actividades de ampliación, recursos en diferentes formatos.'),
        $heading(3, 'Medidas de Nivel III'),
        $para('Acceso o presencia: becas y ayudas, ampliación del tiempo en pruebas, prueba individual en línea, pausa durante la prueba, ubicación específica, anticipar modelo de examen. Adaptaciones de formato (tamaño de letra, fondo, desglose, edición sin tablas, interlineado, Braille, descripciones, escalas de grises, lectura en voz alta).'),
        $para('Participación: asesoramiento sobre itinerarios formativos, acompañamiento emocional personalizado, mejora de habilidades sociales, protocolo de cambio de identidad sexual.'),
        $para('Aprendizaje: adecuación personalizada de programaciones sin modificar contenidos mínimos, adecuación de instrumentos de evaluación, refuerzo/ampliación a medida, material en formatos variados, asesoramiento de itinerarios, adaptaciones para PAU/Accesos/Idiomas.'),
    ]);

    $L($T0, 11, 'Medidas para difundir las buenas prácticas de las TIC', 'locked', [
        $heading(2, "Medidas para difundir las buenas prácticas de las TIC's"),
        $bullet('Hacer un uso correcto, seguro y responsable.'),
        $bullet('Utilizar cuando el docente lo indique.'),
        $bullet('Participar activamente en las actividades planteadas.'),
        $bullet('Acudir a fuentes oficiales.'),
        $bullet('Cotejar la información en, al menos, dos fuentes.'),
        $bullet('Wikipedia y foros solo a modo orientativo.'),
        $bullet('No abrir documentos de origen desconocido.'),
        $bullet('No hacer copia-pega del contenido.'),
        $bullet('Cuidar y respetar los materiales ofrecidos.'),
        $bullet('Cumplir con el formato que se establece y revisar la ortografía.'),
    ]);

    $L($T0, 12, 'Mecanismos de revisión, evaluación y modificación de la programación', 'locked', [
        $heading(2, 'Mecanismos de revisión, evaluación y modificación de la programación'),
        $para('Además de los aprendizajes del alumnado, también se evalúa el proceso de enseñanza y la propia práctica docente en relación al logro de los objetivos educativos del currículo.'),
        $para('Esta evaluación se realiza por: evaluaciones del alumnado mediante tests anónimos (uno al finalizar la primera evaluación y otro al terminar la segunda); evaluación en el departamento didáctico mediante contraste con compañeros; autoevaluación del profesor mediante cuestionarios; evaluación del proyecto curricular interna y externa.'),
        $para('Los indicadores de logro de la práctica docente incluyen: motivación del alumnado, presentación de contenidos, actividades en el aula virtual, recursos y organización, instrucciones y orientaciones, clima del aula virtual, seguimiento del proceso, evaluación y planificación de unidades didácticas.'),
    ]);

    $L($T0, 13, 'Plan de dualización', 'editable', [
        $heading(2, 'Plan de dualización'),
        $para('La FP Dual permite al estudiante recibir formación en el centro educativo y a la vez poner en práctica lo aprendido en un centro de trabajo. El periodo de centro de trabajo se realizará en el segundo curso del ciclo debido a las características específicas del CEEDCV.'),
        $para('Incluir aquí las tablas de horas por módulo y curso (horas/semana, horas/año, porcentaje dual, horas dualizadas) según el Real Decreto del título y la organización del centro.'),
    ]);

    $L($T0, 14, 'Programaciones módulos del ciclo (módulo + profesorado)', 'editable', [
        $heading(2, 'Programaciones módulos del ciclo'),
        $para('Incluir tabla con código, denominación de cada módulo profesional y profesorado asignado para el curso académico. Se mantiene actualizada a lo largo del curso.'),
    ]);

    // -------------------------------------------------------------
    // T1 — Programación didáctica de módulo FP (10 bloques)
    // -------------------------------------------------------------

    $L($T1, 1, 'Cabecera identificación del módulo', 'modifiable', [
        $heading(1, '**NOMBRE_DEL_MÓDULO (CÓDIGO)**'),
        $paraBold('Ciclo formativo: **NOMBRE_CICLO_FORMATIVO**'),
        $paraBold('Horas totales: **HORAS_TOTALES** (**HORAS_SEMANALES**)'),
        $paraBold('Profesorado: **NOMBRE_PROFESORADO**'),
        $para('Sustituye los marcadores en MAYÚSCULAS por los datos reales del módulo antes de publicar.'),
    ]);

    $L($T1, 2, 'Introducción', 'editable', [
        $heading(2, 'Introducción'),
        $heading(3, 'Justificación de la programación'),
        $para('Describir aquí la finalidad del módulo, su ubicación dentro del ciclo, la carga lectiva y la modalidad. Incluir cualquier referencia normativa específica del módulo además de las recogidas en la programación de ciclo.'),
    ]);

    $L($T1, 3, 'Competencias profesionales, personales y sociales', 'editable', [
        $heading(2, 'Competencias profesionales, personales y sociales'),
        $para('Describir la competencia general del título y listar las competencias profesionales, personales y sociales del módulo según el Real Decreto del título.'),
        $para('Tabla con código y descripción de cada competencia que se trabaja en el módulo.'),
    ]);

    $L($T1, 4, 'Resultados de aprendizaje', 'editable', [
        $heading(2, 'Resultados de aprendizaje'),
        $para('Listar los resultados de aprendizaje (RA) del módulo junto con el porcentaje del criterio de calificación asignado.'),
        $para('Tabla con código RA, descripción y porcentaje.'),
    ]);

    $L($T1, 5, 'Criterios de evaluación', 'editable', [
        $heading(2, 'Criterios de evaluación'),
        $para('Por cada RA, listar los criterios de evaluación que constatarán su consecución. Indicar las unidades didácticas en que se trabajará cada criterio.'),
    ]);

    $L($T1, 6, 'Contenidos', 'editable', [
        $heading(2, 'Contenidos'),
        $para('Detallar los contenidos curriculares del módulo organizados en bloques, según la normativa.'),
    ]);

    $L($T1, 7, 'Unidades didácticas', 'editable', [
        $heading(2, 'Unidades didácticas'),
        $heading(3, 'Listado de unidades'),
        $para('Listar las unidades didácticas con su título y duración orientativa.'),
        $heading(3, 'Relación entre unidades didácticas y contenidos del módulo'),
        $para('Tabla UD × bloque de contenidos.'),
        $heading(3, 'Relación entre unidades didácticas y resultados de aprendizaje'),
        $para('Tabla UD × RA.'),
        $heading(3, 'Distribución temporal de las unidades didácticas'),
        $para('Tabla con cuatrimestre, semana de inicio, unidad didáctica y número de semanas.'),
    ]);

    $L($T1, 8, 'Metodología didáctica aplicada', 'locked', [
        $heading(2, 'Metodología didáctica aplicada'),
        $heading(3, 'Principios metodológicos. Metodología general y específica del módulo'),
        $para('Al ser un módulo impartido en enseñanza a distancia, el alumnado dispondrá del material necesario para preparar los contenidos. La materia se distribuye por semanas o quincenas, dependiendo del contenido de la unidad. El alumnado contará con tutorías colectivas (TC) e individuales (TI) de apoyo.'),
        $para('TC: sesiones online de una hora por turno (mañana/tarde) en las que el profesorado resuelve dudas principales y orienta el estudio de la unidad correspondiente. No consisten en impartir todo el contenido, sino en aclarar dudas y reforzar puntos clave.'),
        $para('TI: tutorías individuales online destinadas a la resolución de dudas concretas sobre particularidades del módulo o sobre la realización de evidencias. Requieren cita previa con el profesorado.'),
        $heading(3, 'Materiales y recursos'),
        $para('Material teórico online en el Aula Virtual (Aules). Ejercicios resueltos o metodología de resolución. Ejercicios evaluables y cuestionarios tipo test. Material de apoyo. Plataforma Microsoft Teams y correo corporativo para comunicación.'),
        $heading(3, 'Medidas de atención al alumnado con necesidades educativas específicas'),
        $para('El catálogo de medidas de Nivel II y III adaptadas al entorno de la Formación Profesional en el CEEDCV se detalla en la Programación de Ciclo.'),
    ]);

    $L($T1, 9, 'Evaluación', 'modifiable', [
        $heading(2, 'Evaluación'),
        $heading(3, 'Características de la evaluación'),
        $para('La evaluación tiene por objetivo evidenciar el nivel de desempeño de cada competencia. La calificación se realiza mediante Resultados de Aprendizaje (RA). Es necesario aprobar todos los RA calificados durante el curso (5,00 mínimo en cada uno) para superar el módulo.'),
        $heading(3, 'Criterios de calificación'),
        $tableAsPara(
            ['Componente', '% de la nota'],
            [
                ['Pruebas (exámenes prácticos/escritos)', '**PORCENTAJE_EXAMEN**'],
                ['Tareas y prácticas evaluables', '**PORCENTAJE_TAREAS**'],
                ['Participación y actitud', '**PORCENTAJE_PARTICIPACION**'],
            ]
        ),
        $heading(3, 'Convocatorias'),
        $tableAsPara(
            ['Tipo', 'Fechas', 'Modalidad'],
            [
                ['Evaluación continua 1', '**FECHA_EV1**', 'Online'],
                ['Evaluación continua 2', '**FECHA_EV2**', 'Presencial'],
                ['Convocatoria ordinaria', '**FECHA_ORDINARIA**', 'Presencial'],
                ['Convocatoria extraordinaria', '**FECHA_EXTRAORDINARIA**', 'Presencial'],
            ]
        ),
        $para('Sustituye los marcadores en MAYÚSCULAS por los valores reales del módulo antes de publicar.'),
    ]);

    $L($T1, 10, 'Actividades didácticas complementarias', 'optional', [
        $heading(2, 'Actividades didácticas complementarias'),
        $para('Indicar aquí, si procede, jornadas, charlas, visitas, talleres o cualquier otra actividad complementaria del módulo. Bloque opcional: puede eliminarse si el módulo no contempla actividades específicas más allá de las generales del centro.'),
    ]);

    // -------------------------------------------------------------
    // T2 — Programación didáctica de asignatura Bachillerato (8 bloques)
    // -------------------------------------------------------------

    $L($T2, 1, 'Cabecera identificación de la asignatura', 'modifiable', [
        $heading(1, '**NOMBRE_ASIGNATURA**'),
        $paraBold('Nivel: **NIVEL_BACH** Bachillerato — Modalidad: **MODALIDAD_BACH**'),
        $paraBold('Profesorado: **NOMBRE_PROFESORADO**'),
        $para('Sustituye los marcadores en MAYÚSCULAS por los datos reales de la asignatura antes de publicar.'),
    ]);

    $L($T2, 2, 'Introducción y justificación', 'editable', [
        $heading(2, 'Introducción y justificación'),
        $para('Describir aquí el enfoque de la asignatura dentro del currículo de Bachillerato (LOMLOE), su justificación pedagógica y la relación con los principios y competencias clave de la etapa.'),
    ]);

    $L($T2, 3, 'Contextualización del centro y alumnado', 'locked', [
        $heading(2, 'Contextualización. Características del CEEDCV'),
        $heading(3, 'El centro'),
        $para('El CEEDCV es el Centro Específico de Educación a Distancia de la Comunidad Valenciana, ubicado en el Complejo Educativo La Misericordia (Valencia). Imparte enseñanzas semipresenciales y a distancia, entre ellas el Bachillerato a distancia (NG / LOMLOE).'),
        $heading(3, 'El alumnado'),
        $para('Alumnado mayoritariamente adulto, con perfil dispar: edades, situaciones laborales, formación previa y disponibilidad horaria muy diversas. La modalidad a distancia permite compaginar los estudios con responsabilidades laborales o personales.'),
    ]);

    $L($T2, 4, 'Situaciones de aprendizaje y criterios de evaluación asociados', 'editable', [
        $heading(2, 'Situaciones de aprendizaje y criterios de evaluación asociados'),
        $para('Las competencias específicas se trabajan mediante situaciones de aprendizaje contextualizadas. Por cada situación de aprendizaje, indicar: título, descripción y justificación, recursos y materiales, competencias específicas y criterios de evaluación vinculados, saberes básicos, instrumentos de evaluación y temporalización.'),
    ]);

    $L($T2, 5, 'Temporalización', 'editable', [
        $heading(2, 'Temporalización'),
        $para('Tabla con unidad didáctica, contenido, fechas y número de semanas. Incluir hitos clave: presentación, exámenes de primera y segunda evaluación, convocatoria ordinaria y extraordinaria.'),
    ]);

    $L($T2, 6, 'Aula virtual, metodologías y orientaciones didácticas', 'locked', [
        $heading(2, 'Aula virtual, metodologías y orientaciones didácticas'),
        $para('El Aula Virtual del CEEDCV constituye el elemento central del entorno educativo. Se organiza por unidades didácticas, con secciones para introducción y temporalización, conceptos y diapositivas, píldoras formativas, tutorías colectivas, entregas evaluables, entregas opcionales, soluciones y recursos complementarios.'),
        $para('Recursos disponibles: videotutorías semanales, vídeos cortos aclaratorios, PDF con el contenido de la unidad, enlaces a material adicional.'),
        $para('Materiales didácticos: diapositivas de las videotutorías, ejercicios y actividades prácticas, pequeños cuestionarios de repaso.'),
        $para('Medidas de mejora del rendimiento académico: actividades de refuerzo y ampliación adaptadas a los distintos ritmos de aprendizaje; solución de dificultades de aprendizaje mediante secuenciación adecuada y orientación del profesorado.'),
    ]);

    $L($T2, 7, 'Evaluación', 'modifiable', [
        $heading(2, 'Evaluación'),
        $para('La evaluación continua es inherente al proceso de enseñanza-aprendizaje. Los criterios y porcentajes deben adaptarse al currículo de la asignatura.'),
        $tableAsPara(
            ['Componente', '% de la nota'],
            [
                ['Exámenes', '**PORCENTAJE_EXAMENES**'],
                ['Tareas (actividades evaluables)', '**PORCENTAJE_TAREAS**'],
            ]
        ),
        $para('Convocatorias: evaluación continua (1ª y 2ª evaluación), convocatoria ordinaria de toda la materia (5/10 mínimo para aprobar) y convocatoria extraordinaria (mismas condiciones que la ordinaria). En caso de copia: suspenso de la evaluación continua y obligación de recuperar todo el temario en la convocatoria ordinaria.'),
        $para('Sustituye los marcadores **PORCENTAJE_*** por los valores reales antes de publicar.'),
    ]);

    $L($T2, 8, 'Medidas de respuesta educativa para la inclusión', 'locked', [
        $heading(2, 'Medidas de respuesta educativa para la inclusión'),
        $para('Se aplican los principios de equidad e inclusión del Decreto 104/2018 del Consell y la Orden 20/2019 de la Conselleria. El conjunto de medidas de Nivel II y III, adaptadas al CEEDCV por la comisión de la COCOPE, se detalla en la Programación de Ciclo y aplica de forma análoga a las enseñanzas de Bachillerato.'),
        $para('Medidas de Nivel II: grabación de TC, tareas en diferentes formatos, comunicación personal por TC/TI; foro cafetería, foros de dudas, metodologías activas; ficha tutorial, material de refuerzo y autoformativo, actividades de ampliación.'),
        $para('Medidas de Nivel III: becas y ayudas, ampliación de tiempo en pruebas, adaptaciones de formato (letra, fondo, interlineado, Braille, descripciones), reducción de penalización ortográfica; acompañamiento emocional personalizado; adecuación personalizada de programaciones sin modificar contenidos mínimos.'),
    ]);

    // ============================================================
    // TEMPLATE VERSIONS — 1 publicada por plantilla
    // ============================================================
    // El seeder TemplateVersionsSeeder lee este array y crea entity_versions
    // con version_number>=1. El blocks_snapshot se computa derivándolo de los
    // template_blocks.

    $snapshotFor = static function (string $templateId) use (&$blocks): array {
        $subset = array_values(array_filter($blocks, fn (array $b): bool => $b['template_id'] === $templateId));
        usort($subset, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        return array_map(static fn (array $b): array => [
            'id' => $b['id'],
            'title' => $b['title'],
            'description' => $b['description'],
            'default_content' => $b['default_content'],
            'block_state' => $b['block_state'],
            'sort_order' => $b['sort_order'],
        ], $subset);
    };

    $template_versions = [
        [
            'id' => 'cc000000-0000-4000-8000-000000000000',
            'entity_version_id' => $EV_T0_PUB,
            'template_id' => $T0,
            'version_number' => 1,
            'blocks_snapshot' => $snapshotFor($T0),
            'published_by' => $uDir,
            'published_at' => '2025-09-01 09:00:00',
            'changelog' => 'Publicación inicial — plantilla de programación de ciclo (CEEDCV).',
        ],
        [
            'id' => 'cc000001-0000-4000-8000-000000000000',
            'entity_version_id' => $EV_T1_PUB,
            'template_id' => $T1,
            'version_number' => 1,
            'blocks_snapshot' => $snapshotFor($T1),
            'published_by' => $uDir,
            'published_at' => '2025-09-01 09:00:00',
            'changelog' => 'Publicación inicial — plantilla de programación didáctica de módulo (CEEDCV).',
        ],
        [
            'id' => 'cc000002-0000-4000-8000-000000000000',
            'entity_version_id' => $EV_T2_PUB,
            'template_id' => $T2,
            'version_number' => 1,
            'blocks_snapshot' => $snapshotFor($T2),
            'published_by' => $uDir,
            'published_at' => '2025-09-01 09:00:00',
            'changelog' => 'Publicación inicial — plantilla de programación didáctica de asignatura de Bachillerato (CEEDCV).',
        ],
    ];

    // ============================================================
    // DOCUMENT BLOCKS — instanciados, contenido se carga aparte
    // El parseo de los .md y la generación de los document_blocks
    // vive en programaciones_didacticas_doc_blocks.php
    // ============================================================

    $document_blocks = require __DIR__ . '/programaciones_didacticas_doc_blocks.php';

    return [
        'templates' => $templates,
        'template_blocks' => $blocks,
        'template_versions' => $template_versions,
        'documents' => $documents,
        'document_blocks' => $document_blocks,
    ];
})();
