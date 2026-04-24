<?php

/**
 * Bloques mock de plantilla.
 * Los template_id deben existir en database/data/templates_mock.php.
 */
$programacionPack = require __DIR__ . '/programacion_per_module_templates_pack.php';

$dwesGlobalOfficialContent = require __DIR__.'/dwes_official_programacion_blocknote.php';

/** Párrafo BlockNote compacto para seeds legibles. */
$seedP = static fn (string $text): array => [
    'type' => 'paragraph',
    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
    'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
    'children' => [],
];

$seedH2 = static fn (string $text): array => [
    'type' => 'heading',
    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left', 'level' => 2],
    'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
    'children' => [],
];

$seedDoc = static fn (array $nodes): array => ['type' => 'doc', 'content' => $nodes];

return array_merge([
    [
        'id' => '55555555-5555-5555-5555-555555555501',
        'template_id' => '33333333-3333-3333-3333-333333333301',
        'title' => 'Portada y datos identificativos',
        'description' => $seedDoc([
            $seedP('Plantilla global de prueba: el revisor debe comprobar que el título sea inequívoco y que exista referencia al curso escolar y al centro (ficticio en seed).'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Documento normativo — visibilidad global'),
            $seedP('Sustituye este texto por el título oficial, código interno del documento y versión. Indica responsable de mantenimiento y fecha de próxima revisión programada.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555502',
        'template_id' => '33333333-3333-3333-3333-333333333302',
        'title' => 'Descripción y alcance del equipo',
        'description' => $seedDoc([
            $seedP('Plantilla visible por equipo: valida que se nombre el equipo académico, sus miembros clave y el alcance territorial o de estudios cubiertos.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Contexto del equipo seed'),
            $seedP('Describe el propósito del documento (planificación común, protocolo de evaluación, etc.) y cómo se distribuye el trabajo entre los miembros del equipo.'),
            $seedP('Enlaces internos: repositorio de plantillas, calendario de reuniones y criterios de calidad compartidos (placeholders de ejemplo).'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555503',
        'template_id' => '33333333-3333-3333-3333-333333333303',
        'title' => 'Contexto y alcance del borrador',
        'description' => $seedDoc([
            $seedP('Para el revisor: esta plantilla personal simula el trabajo de un docente ESPA antes de enviar a revisión normativa. Valida que el lenguaje sea adecuado al alumnado adulto y que los criterios sean observables.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Resumen del curso y grupo'),
            $seedP('Sustituye los datos ficticios por el grupo real, aula, horario y ratio. Indica si hay itinerarios de empleabilidad o certificaciones vinculadas.'),
            $seedP('Objetivo del borrador: dejar trazada la programación o memoria que el docente someterá a revisión, con unidades y criterios alineados al decreto autonómico.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555524',
        'template_id' => '33333333-3333-3333-3333-333333333303',
        'title' => 'Riesgos y apoyo al alumnado adulto',
        'description' => $seedDoc([
            $seedP('Guía para revisión: comprueba que existan medidas de conciliación, flexibilidad de entrega y referencias a tutoría u orientación cuando el alumnado combine trabajo y estudio.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Barreras y medidas'),
            $seedP('Conciliación: plazos alternativos para entregas críticas; canal preferente de consulta (correo institucional o tutoría).'),
            $seedP('Diversidad: adaptaciones razonables para competencias básicas y aulas heterogéneas; referencia a protocolos del centro.'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555504',
        'template_id' => '33333333-3333-3333-3333-333333333304',
        'title' => 'Programacion base ESPA (Personas Adultas)',
        'description' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Guia de edicion para docencia ESPA: adapta este bloque al contexto real del grupo adulto, concreta necesidades de conciliacion y empleabilidad, y sustituye ejemplos genericos por evidencias del centro. Mantener siempre relacion entre competencia, situacion de aprendizaje y criterio de calificacion.',
                    'styles' => [],
                ]],
                'children' => [],
            ]],
        ],
        'default_content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Unidad Didactica: La palabra en el trabajo. Contexto: alumnado adulto, con personas trabajando o en busqueda activa de empleo. Competencia especifica: producir textos orales y escritos con coherencia y adecuacion en ambitos profesionales.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Saberes basicos: estructura del Curriculum Vitae y Carta de Presentacion; comprension de textos administrativos basicos (nominas, contratos); cortesia linguistica en entrevista de trabajo.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Situacion de aprendizaje: simulacion de una entrevista laboral. El alumnado redacta su CV real y realiza un role-play aplicando registros formales adecuados.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Criterio de calificacion: 40 por ciento tarea online (CV), 40 por ciento prueba presencial (comprension lectora), 20 por ciento participacion en foro o clase.', 'styles' => []]],
                    'children' => [],
                ],
            ],
        ],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555505',
        'template_id' => '33333333-3333-3333-3333-333333333305',
        'title' => 'Programación 1º ESO',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555506',
        'template_id' => '33333333-3333-3333-3333-333333333306',
        'title' => 'Matemáticas — módulo',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555507',
        'template_id' => '33333333-3333-3333-3333-333333333307',
        'title' => 'Global II',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555508',
        'template_id' => '33333333-3333-3333-3333-333333333308',
        'title' => 'Programacion base Bachillerato',
        'description' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Guia de edicion para docencia Bachillerato: actualizar materia, curso y nivel de dificultad segun grupo real. Alinear de forma explicita criterios de evaluacion con formato EvAU y evidencias que se pediran en examen.',
                    'styles' => [],
                ]],
                'children' => [],
            ]],
        ],
        'default_content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Unidad Didactica: Campo Gravitatorio (Fisica, 2o Bachillerato). Contexto: alumnado con alta presion academica orientado a EvAU y acceso universitario.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Competencia especifica: interpretar interacciones gravitatorias con fisica clasica y ley de gravitacion universal de Newton. Saberes basicos: leyes de Kepler, intensidad y potencial gravitatorio, energia orbital y velocidad de escape.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Situacion de aprendizaje: Mision a Marte. Calculo de velocidad de escape y orbita de transferencia, analizando consumo energetico en un escenario tipo EvAU.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Criterio de calificacion: 80 por ciento examenes teoricos y problemas (formato EvAU), 10 por ciento practicas de laboratorio o simuladores, 10 por ciento actitud y trabajo diario.', 'styles' => []]],
                    'children' => [],
                ],
            ],
        ],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555509',
        'template_id' => '33333333-3333-3333-3333-333333333309',
        'title' => 'Programacion base FP (Ciclos Medio/Superior)',
        'description' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Guia de edicion para docencia FP: personalizar este bloque con resultados de aprendizaje oficiales del modulo (BOE/DOG/BOJA), detallar entorno tecnico real del aula y definir criterios verificables de funcionamiento, documentacion y optimizacion.',
                    'styles' => [],
                ]],
                'children' => [],
            ]],
        ],
        'default_content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Unidad de trabajo: Virtualizacion y Contenedores. Contexto: futuros tecnicos ASIR/DAW que deben desplegar entornos para empresa. RA3: instala software de virtualizacion y configura maquinas virtuales, analizando su funcionamiento.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Saberes basicos: hipervisores Type 1 y Type 2; redes virtuales NAT, Bridge y Host-only; introduccion a Docker (imagenes, contenedores y volumenes).', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Reto profesional: Infraestructura para una Startup. Levantar servidor web, base de datos y balanceador con Docker, con comunicacion interna entre servicios y base de datos no expuesta externamente.', 'styles' => []]],
                    'children' => [],
                ],
                [
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => 'Criterio de evaluacion: funcionamiento tecnico del despliegue 60 por ciento, documentacion tecnica 30 por ciento, optimizacion de recursos 10 por ciento.', 'styles' => []]],
                    'children' => [],
                ],
            ],
        ],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555510',
        'template_id' => '33333333-3333-3333-3333-333333333310',
        'title' => 'Ciclo DAW',
        'default_content' => null,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555511',
        'template_id' => '33333333-3333-3333-3333-333333333311',
        'title' => 'DWES',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555512',
        'template_id' => '33333333-3333-3333-3333-333333333312',
        'title' => 'Identificación y competencias del módulo',
        'description' => $seedDoc([
            $seedP('Revisor: verifica coherencia con el currículo de Bachillerato del centro, mención explícita a competencias y resultados de aprendizaje, y adecuación al calendario de EvAU si aplica.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Datos del módulo y del departamento'),
            $seedP('Indica materia, curso, nivel (Ciencias / Humanidades), grupo y horas lectivas. Nombra al coordinador de departamento y los acuerdos de evaluación compartidos.'),
            $seedP('Competencias específicas: lista tres competencias trabajadas en el trimestre con indicadores observables en el aula (rúbrica o lista de chequeo).'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555525',
        'template_id' => '33333333-3333-3333-3333-333333333312',
        'title' => 'Secuenciación y evaluación',
        'description' => $seedDoc([
            $seedP('Comprueba que existan instrumentos de evaluación formativa y sumativa, ponderaciones orientativas y criterios de recuperación alineados con el departamento.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Temporalización y pruebas'),
            $seedP('Unidad 1: diagnóstico y revisión de prerrequisitos. Unidad 2: núcleo conceptual con práctica en aula. Unidad 3: síntesis y prueba integradora tipo EvAU.'),
            $seedP('Evaluación: 40 % prueba escrita, 40 % práctica o proyecto, 20 % actitud y trabajo cotidiano (ajustar según normativa del centro).'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555513',
        'template_id' => '33333333-3333-3333-3333-333333333313',
        'title' => 'Ficha del módulo FP y resultados de aprendizaje',
        'description' => $seedDoc([
            $seedP('Revisor: exige referencia explícita al BOE del módulo, RA y criterios de evaluación, y coherencia con el proyecto integrado del ciclo si procede.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Identificación del ciclo y del módulo'),
            $seedP('Ciclo formativo, código de módulo, horas lectivas y curso. Relación con otros módulos del ciclo y con las prácticas en empresa si aplica.'),
            $seedP('RA trabajados en el trimestre: selecciona los más relevantes y enlázalos con unidades didácticas y evidencias de evaluación.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555526',
        'template_id' => '33333333-3333-3333-3333-333333333313',
        'title' => 'Proyecto práctico y criterios de calidad',
        'description' => $seedDoc([
            $seedP('Valida que el proyecto integre varios RA, incluya entregables intermedios y criterios de calidad (seguridad, documentación, despliegue o pruebas según el módulo).'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Enunciado y rúbrica orientativa'),
            $seedP('Describe el reto profesional, entorno técnico (laboratorio, aula digital, taller), roles del alumnado y hitos de entrega.'),
            $seedP('Rúbrica: funcionalidad, calidad del código o procedimiento, documentación técnica y presentación oral o memoria final.'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555514',
        'template_id' => '33333333-3333-3333-3333-333333333314',
        'title' => 'Objeto y circuito de esta plantilla personal',
        'description' => $seedDoc([
            $seedP('Para Secretaría (revisión): plantilla de prueba con visibilidad personal. Comprueba que el texto explique el uso interno del DMS, el orden de validaciones y los contactos de calidad antes de aprobar.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Uso previsto en el DMS'),
            $seedP('Esta plantilla simula documentación administrativa o de calidad elaborada por Secretaría antes de publicar modelos para el centro. Sustituye referencias genéricas por procedimientos reales del centro (ISO, PEC, calendario de secretaría).'),
            $seedP('Circuito: borrador → revisión (Dirección / Auditoría según configuración) → publicación o devolución con comentarios en bloques.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555520',
        'template_id' => '33333333-3333-3333-3333-333333333314',
        'title' => 'Checklist administrativo y trazabilidad',
        'description' => $seedDoc([
            $seedP('Revisa que consten versiones de normativa citadas, plazos de tramitación y referencias a registros (expediente, número de registro, responsable).'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Controles mínimos'),
            $seedP('Identificación del procedimiento, ámbito (ESO, Bach, FP), responsable y fecha de última revisión.'),
            $seedP('Anexos: enlaces a formularios oficiales, plantillas de comunicación a familias o empresas, y comprobaciones de cumplimiento (LOMLOE, convenios, RGPD).'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555521',
        'template_id' => '33333333-3333-3333-3333-333333333314',
        'title' => 'Observaciones a Dirección y Auditoría',
        'description' => $seedDoc([
            $seedP('Espacio para que la validación deje constancia de incidencias, riesgos legales o mejoras sugeridas antes de aceptar el modelo.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Incidencias y seguimiento'),
            $seedP('Lista hallazgos con severidad (baja / media / alta) y acción recomendada. Incluye plazo de corrección si la plantilla se devuelve a borrador.'),
            $seedP('Si no hay incidencias, indica explícitamente "Sin observaciones" para dejar trazabilidad en auditoría.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 2,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555515',
        'template_id' => '33333333-3333-3333-3333-333333333315',
        'title' => 'Programación docente — Bachillerato (borrador personal)',
        'description' => $seedDoc([
            $seedP('Guía de revisión: alinea con el departamento de la materia, menciona criterios EvAU donde proceda y deja explícita la coordinación con otros módulos del curso.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Contexto del grupo y objetivos generales'),
            $seedP('Describe el perfil del alumnado, apoyos a la diversidad y objetivos generales del curso que condicionan la programación del docente.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555527',
        'template_id' => '33333333-3333-3333-3333-333333333315',
        'title' => 'Unidades, metodología y evaluación',
        'description' => $seedDoc([
            $seedP('Comprueba que las unidades tengan duración orientativa, metodología activa descrita y evaluación alineada con los estándares de aprendizaje evaluables.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Secuencia didáctica'),
            $seedP('UT1: diagnóstico y revisión de competencias previas. UT2–4: núcleo conceptual con prácticas en aula. UT5: proyecto o estudio de caso integrador.'),
            $seedP('Evaluación: instrumentos (pruebas, rúbricas, portafolio), ponderaciones orientativas y criterios de recuperación.'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555516',
        'template_id' => '33333333-3333-3333-3333-333333333316',
        'title' => '2º ESO',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555517',
        'template_id' => '33333333-3333-3333-3333-333333333317',
        'title' => 'Inglés 1º ESO',
        'default_content' => ['type' => 'doc', 'content' => []],
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555518',
        'template_id' => '33333333-3333-3333-3333-333333333318',
        'title' => 'Programación didáctica — DWES (oficial, catálogo global)',
        'default_content' => $dwesGlobalOfficialContent,
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555519',
        'template_id' => '33333333-3333-3333-3333-333333333319',
        'title' => 'Resumen ejecutivo y alcance',
        'description' => $seedDoc([
            $seedP('Plantilla personal publicada de Secretaría (seed). Sirve como modelo estable: al clonarla, los documentos heredarán esta estructura. Revisa coherencia con procedimientos del centro.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Modelo publicado para el DMS'),
            $seedP('Plantilla de referencia publicada por Secretaría (seed). Los documentos generados se validan con Dirección y Auditoría según la configuración de revisores y el orden de etapas definido en la plantilla.'),
            $seedP('Sustituye los textos orientativos por la versión oficial aprobada en el claustro o en el consejo escolar, incluyendo referencias normativas y fechas de vigencia.'),
        ]),
        'block_state' => 'editable',
        'sort_order' => 0,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555522',
        'template_id' => '33333333-3333-3333-3333-333333333319',
        'title' => 'Validaciones cruzadas (Dirección / Auditoría)',
        'description' => $seedDoc([
            $seedP('Indica qué aspectos debe comprobar cada rol: legalidad de comunicaciones, coherencia curricular, protección de datos y trazabilidad de firmas o registros.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Roles y comprobaciones'),
            $seedP('Dirección: alineación con el proyecto educativo del centro y calendario de implantación.'),
            $seedP('Auditoría: muestra de evidencias, control de versiones y cumplimiento de retención documental.'),
        ]),
        'block_state' => 'modifiable',
        'sort_order' => 1,
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555523',
        'template_id' => '33333333-3333-3333-3333-333333333319',
        'title' => 'Anexos y contactos',
        'description' => $seedDoc([
            $seedP('Enlaces a formularios, tablas de plazos y datos de contacto institucional. Mantén el bloque aunque sea breve para pruebas de exportación o impresión.'),
        ]),
        'default_content' => $seedDoc([
            $seedH2('Referencias y soporte'),
            $seedP('Anexos: plantillas de comunicación a familias, empresas o administración; extractos de normativa aplicable.'),
            $seedP('Contactos: secretaría académica, dirección, coordinación TIC y delegado de protección de datos (ajustar al organigrama real).'),
        ]),
        'block_state' => 'optional',
        'sort_order' => 2,
    ],
], $programacionPack['template_blocks']);
