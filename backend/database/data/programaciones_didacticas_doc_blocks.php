<?php

declare(strict_types=1);

/**
 * Document blocks instanciados para los 5 documentos del pack
 * `programaciones_didacticas_pack.php`.
 *
 * Convención:
 *  - Sólo se generan document_blocks para los bloques `editable`,
 *    `modifiable` y `optional` que tienen contenido sustantivo en
 *    el documento fuente.
 *  - Los bloques `locked` NO se sobreescriben: en runtime,
 *    `DocumentBlockService::blocksForDisplay()` hereda el
 *    `default_content` del `template_block` correspondiente.
 *  - El contenido proviene íntegro de los .md de cada módulo
 *    (programacion_ciclo.md, 25_26_DAW_0613_DWES.md,
 *    25_26_DAW_1709_IPO I.md, 25_26_TL_0626_LAP.md,
 *    25_26_BATX1_PXSI1.md) — sin parafraseo, sólo conversión
 *    Markdown → BlockNote.
 *  - Tablas → `paragraph` único con saltos de línea y pipes
 *    (mismo patrón que `dwes_official_programacion_blocknote.php`).
 *
 * @return list<array<string, mixed>>
 */

return (static function (): array {
    // -- Owners (maya_dev_users.php + documents del pack) --
    $devUsers = require __DIR__ . '/maya_dev_users.php';
    $u = static fn (string $key): string => $devUsers[$key]
        ?? throw new \InvalidArgumentException("Usuario dev desconocido: {$key}");

    $uJefeDI = $u('jefe_d_i');       // D0 (Ciclo ASIR)
    $uFp = $u('docente_i');          // D1, D2 (módulos FP informática)
    $uJefeEFp = $u('jefe_e_fp');     // D3 (LAP / TIL)
    $uBach = $u('docente_b');        // D4 (Bachillerato)

    // -- Documentos --
    $D0 = 'dd000000-0000-4000-8000-000000000000'; // Ciclo ASIR
    $D1 = 'dd000001-0000-4000-8000-000000000000'; // DWES
    $D2 = 'dd000002-0000-4000-8000-000000000000'; // IPO I
    $D3 = 'dd000003-0000-4000-8000-000000000000'; // LAP
    $D4 = 'dd000004-0000-4000-8000-000000000000'; // PXSI1

    // -- Helpers UUID template_blocks (T0, T1, T2) --
    $T0B = static fn (int $n): string => sprintf('bb000000-0000-4000-8000-%012d', $n);
    $T1B = static fn (int $n): string => sprintf('bb000001-0000-4000-8000-%012d', $n);
    $T2B = static fn (int $n): string => sprintf('bb000002-0000-4000-8000-%012d', $n);

    // -- BlockNote helpers --
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

    /**
     * Construye un document_block. El UUID es determinista:
     *   dd<docIdx:06d>-0000-4000-8000-<blockIdx:012d>
     * donde docIdx es 0..4 (D0..D4) y blockIdx es el sort_order del bloque
     * dentro de la plantilla correspondiente (1..14).
     */
    $mkBlock = static function (
        string $docId,
        string $ownerId,
        string $templateBlockId,
        int $docIdx,
        int $blockIdx,
        array $content,
        bool $isFilled = true
    ): array {
        return [
            'id' => sprintf('dd%06d-0000-4000-8000-%012d', $docIdx, $blockIdx),
            'document_id' => $docId,
            'template_block_id' => $templateBlockId,
            'content' => $content,
            'is_filled' => $isFilled,
            'last_edited_by' => $ownerId,
            'locked_by' => null,
            'locked_at' => null,
            'sort_order' => $blockIdx,
        ];
    };

    $blocks = [];

    // ============================================================
    // D0 — Ciclo ASIR (T0) — bloques: 1, 2, 5, 6, 8, 9, 13, 14
    // (3, 4, 7, 10, 11, 12 son locked → no se crean)
    // ============================================================

    // T0 / Block 1 — Cabecera (modifiable) — sustituye placeholders
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(1), 0, 1, [
        $heading(1, 'CICLO FORMATIVO DE ADMINISTRACIÓN DE SISTEMAS INFORMÁTICOS EN RED'),
        $paraBold('Departamento: Informática y Comunicaciones'),
        $paraBold('Jefe de departamento: Óscar Villar Fernández'),
    ]);

    // T0 / Block 2 — Identificación del título (editable)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(2), 0, 2, [
        $heading(2, 'Identificación del título'),
        $para('La formación en general y la formación profesional en particular, constituyen hoy en día objetivos prioritarios de cualquier país que se plantee estrategias de crecimiento económico, de desarrollo tecnológico y de mejora de la calidad de vida de sus ciudadanos ante una realidad que manifiesta claros síntomas de cambio acelerado, especialmente en el campo tecnológico.'),
        $para('Esta formación de tipo polivalente debe permitir a los ciudadanos adaptarse a los cambios en la normativa laboral que puedan producirse a lo largo de su vida. La estructura y organización de las enseñanzas profesionales, sus objetivos y contenidos, así como los criterios de evaluación, son enfocados en la ordenación de la formación profesional desde la perspectiva de la adquisición de la competencia profesional.'),
        $para('Concretamente, con el título de formación profesional de Técnico Superior en Administración de Sistemas Informáticos en Red se debe adquirir la competencia general de configurar, administrar y mantener sistemas informáticos, garantizando la funcionalidad, la integridad de los recursos y servicios del sistema, con la calidad exigida y cumpliendo la reglamentación vigente.'),
        $para('El ciclo superior de Administración de Sistemas Informáticos en Red se basa fundamentalmente en el desarrollo individual del material didáctico y la comunicación con los profesores de cada uno de los módulos, tanto en las tutorías individuales como en las colectivas o virtuales. Es por tanto un sistema que supone un elevado esfuerzo individual y un trabajo continuo en el proceso de aprendizaje.'),
        $para('El título de Técnico en Administración de Sistemas Informáticos en Red queda identificado por los siguientes elementos:'),
        $tableAsPara(
            ['Elemento', 'Valor'],
            [
                ['Denominación', 'Administración de Sistemas Informáticos en Red.'],
                ['Nivel', 'Formación Profesional de Grado Superior.'],
                ['Duración', '2.000 horas.'],
                ['Familia profesional', 'Informática y Comunicaciones'],
                ['Referente CINE', 'CINE-5b.'],
                ['Nivel MECES', 'Nivel 1 Técnico Superior.'],
            ]
        ),
    ]);

    // T0 / Block 5 — Objetivos del ciclo (editable)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(5), 0, 5, [
        $heading(2, 'Objetivos del ciclo'),
        $para('El presente elemento curricular se encuentra regulado en el RD 1629/2009, de 4 de noviembre. De manera más concreta, en el artículo 9, Capítulo III "Enseñanzas del ciclo formativo y parámetros básicos de contexto".'),
        $para('Los objetivos generales se refieren a la totalidad del ciclo formativo, ya que son objetivos estratégicos comunes a todos los módulos. Así, estos sirven de guía y orientación para la acción docente.'),
        $para('Los objetivos generales según el RD 1629/2009 de este ciclo formativo son los siguientes:'),
        $bullet('Analizar la estructura del software de base, comparando las características y prestaciones de sistemas libres y propietarios, para administrar sistemas operativos de servidor.'),
        $bullet('Instalar y configurar el software de base, siguiendo documentación técnica y especificaciones dadas, para administrar sistemas operativos de servidor.'),
        $bullet('Instalar y configurar software de mensajería y transferencia de ficheros, entre otros, relacionándolos con su aplicación y siguiendo documentación y especificaciones dadas, para administrar servicios de red.'),
        $bullet('Instalar y configurar software de gestión, siguiendo especificaciones y analizando entornos de aplicación, para administrar aplicaciones.'),
        $bullet('Instalar y administrar software de gestión, relacionándolo con su explotación, para implantar y gestionar bases de datos.'),
        $bullet('Configurar dispositivos hardware, analizando sus características funcionales, para optimizar el rendimiento del sistema.'),
        $bullet('Configurar hardware de red, analizando sus características funcionales y relacionándolo con su campo de aplicación, para integrar equipos de comunicaciones.'),
        $bullet('Analizar tecnologías de interconexión, describiendo sus características y posibilidades de aplicación, para configurar la estructura de la red telemática y evaluar su rendimiento.'),
        $bullet('Elaborar esquemas de redes telemáticas utilizando software específico para configurar la estructura de la red telemática.'),
        $bullet('Seleccionar sistemas de protección y recuperación, analizando sus características funcionales, para poner en marcha soluciones de alta disponibilidad.'),
        $bullet('Identificar condiciones de equipos e instalaciones, interpretando planes de seguridad y especificaciones de fabricante, para supervisar la seguridad física.'),
        $bullet('Aplicar técnicas de protección contra amenazas externas, tipificándolas y evaluándolas para asegurar el sistema.'),
        $bullet('Aplicar técnicas de protección contra pérdidas de información, analizando planes de seguridad y necesidades de uso para asegurar los datos.'),
        $bullet('Asignar los accesos y recursos del sistema, aplicando las especificaciones de la explotación, para administrar usuarios.'),
        $bullet('Aplicar técnicas de monitorización interpretando los resultados y relacionándolos con las medidas correctoras para diagnosticar y corregir las disfunciones.'),
        $bullet('Establecer la planificación de tareas, analizando actividades y cargas de trabajo del sistema para gestionar el mantenimiento.'),
        $bullet('Identificar los cambios tecnológicos, organizativos, económicos y laborales en su actividad, analizando sus implicaciones en el ámbito de trabajo, para resolver problemas y mantener una cultura de actualización e innovación.'),
        $bullet('Identificar formas de intervención en situaciones colectivas, analizando el proceso de toma de decisiones y efectuando consultas para liderar las mismas.'),
        $bullet('Identificar y valorar las oportunidades de aprendizaje y su relación con el mundo laboral, analizando las ofertas y demandas del mercado para gestionar su carrera profesional.'),
        $bullet('Reconocer las oportunidades de negocio, identificando y analizando demandas del mercado para crear y gestionar una pequeña empresa.'),
        $bullet('Reconocer sus derechos y deberes como agente activo en la sociedad, analizando el marco legal que regula las condiciones sociales y laborales para participar como ciudadano democrático.'),
    ]);

    // T0 / Block 6 — Competencias (editable)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(6), 0, 6, [
        $heading(2, 'Competencias'),
        $para('Las competencias son un "conjunto complejo de conocimientos, habilidades, actitudes, valores, emociones y motivaciones que cada individuo o cada grupo pone en acción en un contexto concreto para hacer frente a las demandas peculiares de cada situación".'),
        $para('En concreto, el capítulo II del título de Técnico Superior en Administración de Sistemas Informáticos en Red, diferencia entre competencia general y competencias profesionales, personales y sociales. La competencia general toma como referente el conjunto de cualificaciones profesionales y las unidades de competencia incluidas en el Catálogo Nacional de Cualificaciones Profesionales.'),
        $heading(3, 'Competencia general'),
        $para('Siguiendo con el artículo 4 del RD 1629/2009, 30 de octubre, la competencia general de este título consiste en "configurar, administrar y mantener sistemas informáticos, garantizando la funcionalidad, la integridad de los recursos y servicios del sistema, con la calidad exigida y cumpliendo la reglamentación vigente".'),
        $heading(3, 'Competencias profesionales, personales y sociales'),
        $para('Las competencias profesionales, personales y sociales describen el conjunto de conocimientos, destrezas y competencia que permiten responder a los requerimientos del sector productivo, aumentar la empleabilidad y favorecer la cohesión social. Las que establece el RD de Título son las que se relacionan a continuación:'),
        $bullet('Administrar sistemas operativos de servidor, instalando y configurando el software, en condiciones de calidad para asegurar el funcionamiento del sistema.'),
        $bullet('Administrar servicios de red (web, mensajería electrónica y transferencia de archivos, entre otros) instalando y configurando el software, en condiciones de calidad.'),
        $bullet('Administrar aplicaciones instalando y configurando el software, en condiciones de calidad para responder a las necesidades de la organización.'),
        $bullet('Implantar y gestionar bases de datos instalando y administrando el software de gestión en condiciones de calidad, según las características de la explotación.'),
        $bullet('Optimizar el rendimiento del sistema configurando los dispositivos hardware de acuerdo a los requisitos de funcionamiento.'),
        $bullet('Evaluar el rendimiento de los dispositivos hardware identificando posibilidades de mejoras según las necesidades de funcionamiento.'),
        $bullet('Determinar la infraestructura de redes telemáticas elaborando esquemas y seleccionando equipos y elementos.'),
        $bullet('Integrar equipos de comunicaciones en infraestructuras de redes telemáticas, determinando la configuración para asegurar su conectividad.'),
        $bullet('Implementar soluciones de alta disponibilidad, analizando las distintas opciones del mercado, para proteger y recuperar el sistema ante situaciones imprevistas.'),
        $bullet('Supervisar la seguridad física según especificaciones del fabricante y el plan de seguridad para evitar interrupciones en la prestación de servicios del sistema.'),
        $bullet('Asegurar el sistema y los datos según las necesidades de uso y las condiciones de seguridad establecidas para prevenir fallos y ataques externos.'),
        $bullet('Administrar usuarios de acuerdo a las especificaciones de explotación para garantizar los accesos y la disponibilidad de los recursos del sistema.'),
        $bullet('Diagnosticar las disfunciones del sistema y adoptar las medidas correctivas para restablecer su funcionalidad.'),
        $bullet('Gestionar y/o realizar el mantenimiento de los recursos de su área (programando y verificando su cumplimiento), en función de las cargas de trabajo y el plan de mantenimiento.'),
        $bullet('Efectuar consultas, dirigiéndose a la persona adecuada y saber respetar la autonomía de los subordinados, informando cuando sea conveniente.'),
        $bullet('Mantener el espíritu de innovación y actualización en el ámbito de su trabajo para adaptarse a los cambios tecnológicos y organizativos de su entorno profesional.'),
        $bullet('Liderar situaciones colectivas que se puedan producir, mediando en conflictos personales y laborales, contribuyendo al establecimiento de un ambiente de trabajo agradable y actuando en todo momento de forma sincera, respetuosa y tolerante.'),
        $bullet('Resolver problemas y tomar decisiones individuales, siguiendo las normas y procedimientos establecidos, definidos dentro del ámbito de su competencia.'),
        $bullet('Gestionar su carrera profesional, analizando las oportunidades de empleo, autoempleo y de aprendizaje.'),
        $bullet('Participar de forma activa en la vida económica, social y cultural con actitud crítica y responsable.'),
        $bullet('Crear y gestionar una pequeña empresa, realizando un estudio de viabilidad de productos, de planificación de la producción y de comercialización.'),
    ]);

    // T0 / Block 8 — Evaluación (modifiable) — sustituye fechas reales del calendario ASIR
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(8), 0, 8, [
        $heading(2, 'EVALUACIÓN'),
        $heading(3, 'Tipos de evaluación'),
        $para('La primera evaluación se realizará en formato "a distancia" y la segunda evaluación y evaluaciones ordinaria y extraordinaria se realizarán de forma presencial, en el CEEDCV en las aulas asignadas para tal efecto, de las cuales se informará tanto en el aula de tutoría como en las aulas de los módulos correspondientes.'),
        $tableAsPara(
            ['EVALUACIÓN', 'TIPO', 'DESDE', 'HASTA', 'EXAMEN'],
            [
                ['CONTINUA', 'EVALUACIÓN 1', '13/01', '17/01', 'Online'],
                ['CONTINUA', 'EVALUACIÓN 2', '05/05', '09/05', 'Presencial'],
                ['ORDINARIA', 'FINAL', '26/05', '30/05', 'Presencial'],
                ['EXTRAORDINARIA', 'FINAL', '16/06', '20/06', 'Presencial'],
            ]
        ),
    ]);

    // T0 / Block 9 — Actividades complementarias y extraescolares (optional)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(9), 0, 9, [
        $heading(2, 'Actividades complementarias y extraescolares'),
        $para('No se contemplan actividades extraescolares en este ciclo, aunque a lo largo del curso se indicarán a los alumnos jornadas (p.ej., jornadas de empleabilidad, jornadas de talento), cursos y seminarios que puedan servir de interés para su desarrollo profesional.'),
    ]);

    // T0 / Block 13 — Plan de dualización (editable)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(13), 0, 13, [
        $heading(2, 'Plan de dualización'),
        $para('La FP Dual permite al estudiante recibir una formación en el centro educativo y al mismo tiempo poner en práctica lo aprendido en un centro de trabajo. El periodo que se llevará en el centro de trabajo se desarrollará en el segundo curso del ciclo debido a las características específicas del CEEDCV.'),
        $para('En las siguientes tablas se muestran las horas que los alumnos realizarán en el centro de trabajo de cada módulo, así como los totales por curso.'),
        $tableAsPara(
            ['Código', 'Módulos 1º', 'hrs/sem', 'hrs/año', 'Dual %', 'Horas dual'],
            [
                ['0179', 'Inglés profesional GS', '2', '64', '30%', '18'],
                ['0369', 'Implantación de sistemas operativos', '7', '224', '30%', '67'],
                ['0370', 'Planificación y administración de redes', '6', '192', '28%', '54'],
                ['0371', 'Fundamentos de hardware', '3', '96', '34%', '33'],
                ['0372', 'Gestión de bases de datos', '5', '160', '28%', '45'],
                ['0373', 'Lenguajes de marcas y sistemas de gestión de información', '3', '96', '30%', '29'],
                ['01709', 'Itinerario personal para la empleabilidad I', '3', '96', '30%', '29'],
                ['1713A', 'Proyecto intermodular', '1', '32', '0%', '0'],
                ['829104B', 'Horario reservado al desarrollo de la competencia profesional', '', '40', '0%', '0'],
                ['', 'Total 1º ASIR', '30', '1000', '', '265'],
            ]
        ),
        $tableAsPara(
            ['Código', 'Módulos 2º', 'hrs/sem', 'hrs/año', 'Dual %', 'Horas dual'],
            [
                ['0374', 'Administración de sistemas operativos', '4', '120', '28%', '33'],
                ['0375', 'Servicios de red e internet', '4', '120', '28%', '33'],
                ['0376', 'Implantación de aplicaciones web', '5', '100', '28%', '28'],
                ['0377', 'Administración de sistemas gestores de bases de datos', '3', '60', '30%', '18'],
                ['0378', 'Seguridad y alta disponibilidad', '5', '100', '30%', '30'],
                ['1665', 'Digitalización aplicada al sistema productivo GS', '1', '32', '65%', '21'],
                ['1708', 'Sostenibilidad aplicada al sistema productivo', '1', '32', '65%', '21'],
                ['1710', 'Itinerario personal para la empleabilidad II', '3', '96', '30%', '29'],
                ['1713B', 'Proyecto intermodular', '2', '128', '0%', '0'],
                ['Cvopt', 'Optativa', '3', '96', '32%', '31'],
                ['829104B', 'Horario reservado al desarrollo de la competencia profesional', '', '116', '0%', '0'],
                ['', 'Total 2º ASIR', '30', '1000', '', '259'],
            ]
        ),
    ]);

    // T0 / Block 14 — Programaciones módulos del ciclo (editable)
    $blocks[] = $mkBlock($D0, $uJefeDI, $T0B(14), 0, 14, [
        $heading(2, 'Programaciones módulos del ciclo'),
        $heading(3, 'CFGS Administración de Sistemas Informáticos en Red — Primer Curso'),
        $tableAsPara(
            ['Módulo Profesional', 'Profesorado'],
            [
                ['0179. Inglés profesional', 'Rut Villar Sánchez'],
                ['0369 Implantación de sistemas operativos', 'Óscar Villar Fernández'],
                ['0370 Planificación y administración de redes', 'Raúl Palao Lozano'],
                ['0371 Fundamentos de hardware', 'Luis Fortich Giner'],
                ['0372 Gestión de bases de datos', 'Pau Miñana Climent'],
                ['0373 Lenguajes de marcas y sistemas de gestión de información', 'Carlos Alcañiz Carbonell'],
                ['01709. Itinerario personal para la empleabilidad I', 'Nuria Gimeno Lliso'],
                ['1713A Proyecto intermodular', 'Carlos Alcañiz Carbonell'],
            ]
        ),
    ]);

    // ============================================================
    // D1 — DWES (T1) — bloques: 1, 2, 3, 4, 5, 6, 7, 9, 10
    // (8 metodología es locked → no se crea)
    // ============================================================

    // T1 / Block 1 — Cabecera DWES (modifiable)
    $blocks[] = $mkBlock($D1, $uFp, $T1B(1), 1, 1, [
        $heading(1, 'Desarrollo Web en Entorno Servidor (0613)'),
        $paraBold('Ciclo formativo: Desarrollo de Aplicaciones Web (2º CFGS)'),
        $paraBold('Horas totales: 200 horas (6 horas/semana)'),
        $paraBold('Profesorado: Guillermo Garrido Portes'),
    ]);

    // T1 / Block 2 — Introducción DWES
    $blocks[] = $mkBlock($D1, $uFp, $T1B(2), 1, 2, [
        $heading(2, 'Introducción'),
        $heading(3, 'Justificación de la programación'),
        $para('Esta programación didáctica corresponde al módulo formativo Desarrollo Web en Entorno Servidor que forma parte del segundo curso del ciclo formativo de grado superior de Desarrollo de Aplicaciones Web de la familia de Informática. Este ciclo se distribuye en dos cursos con un total de 2.000 horas, de los cuales 200 corresponden a dicho módulo, que se imparte en el segundo curso a razón de 6 horas semanales y en la modalidad online. El resto de normativa por la que se regula esta programación queda recogida en la programación de ciclo.'),
    ]);

    // T1 / Block 3 — Competencias DWES
    $blocks[] = $mkBlock($D1, $uFp, $T1B(3), 1, 3, [
        $heading(2, 'Competencias profesionales, personales y sociales'),
        $para('La competencia general de este título consiste en desarrollar, implantar y mantener aplicaciones web, con independencia del modelo empleado y utilizando tecnologías específicas, garantizando el acceso a los datos de forma segura y cumpliendo con los criterios de accesibilidad, usabilidad y calidad exigidos en los estándares establecidos.'),
        $para('La formación del módulo contribuye a alcanzar las siguientes competencias del título:'),
        $tableAsPara(
            ['Código', 'Competencia'],
            [
                ['c', 'Gestionar servidores de aplicaciones adaptando su configuración en cada caso para permitir el despliegue de aplicaciones web.'],
                ['d', 'Gestionar bases de datos, interpretando su diseño lógico y verificando integridad, consistencia, seguridad y accesibilidad de los datos.'],
                ['f', 'Integrar contenidos en la lógica de una aplicación web, desarrollando componentes de acceso a datos adecuados a las especificaciones.'],
                ['g', 'Desarrollar interfaces en aplicaciones web de acuerdo con un manual de estilo, utilizando lenguajes de marcas y estándares web.'],
                ['h', 'Desarrollar componentes multimedia para su integración en aplicaciones web, empleando herramientas específicas y siguiendo las especificaciones establecidas.'],
                ['j', 'Desarrollar aplicaciones para teléfonos móviles, tabletas y otros dispositivos inteligentes empleando técnicas y entornos de desarrollo específicos.'],
                ['k', 'Desarrollar servicios para integrar sus funciones en otras aplicaciones web, asegurando su funcionalidad.'],
                ['l', 'Integrar servicios y contenidos distribuidos en aplicaciones web, asegurando su funcionalidad.'],
                ['m', 'Completar planes de pruebas verificando el funcionamiento de los componentes software desarrollados, según las especificaciones.'],
                ['n', 'Elaborar y mantener la documentación de los procesos de desarrollo, utilizando herramientas de generación de documentación y control de versiones.'],
                ['ñ', 'Desplegar y distribuir aplicaciones web en distintos ámbitos de implantación, verificando su comportamiento y realizando modificaciones.'],
                ['q', 'Resolver situaciones, problemas o contingencias con iniciativa y autonomía en el ámbito de su competencia, con creatividad, innovación y espíritu de mejora.'],
            ]
        ),
    ]);

    // T1 / Block 4 — Resultados de aprendizaje DWES
    $blocks[] = $mkBlock($D1, $uFp, $T1B(4), 1, 4, [
        $heading(2, 'Resultados de aprendizaje'),
        $para('Los objetivos del módulo de Desarrollo Web en Entorno Servidor aparecen en el Real Decreto descrito en la Programación de Ciclo, en forma de resultados de aprendizaje (en adelante RA). A continuación, se desglosan en la siguiente tabla los resultados de aprendizaje del título junto con el porcentaje del criterio de calificación que se le asigna:'),
        $tableAsPara(
            ['Código', 'Resultado de aprendizaje', '% RA'],
            [
                ['RA01', 'Selecciona las arquitecturas y tecnologías de programación Web en entorno servidor, analizando sus capacidades y características propias.', '5,8'],
                ['RA02', 'Escribe sentencias ejecutables por un servidor Web reconociendo y aplicando procedimientos de integración del código en lenguajes de marcas.', '5,8'],
                ['RA03', 'Escribe bloques de sentencias embebidos en lenguajes de marcas, seleccionando y utilizando las estructuras de programación.', '5,8'],
                ['RA04', 'Desarrolla aplicaciones Web embebidas en lenguajes de marcas analizando e incorporando funcionalidades según especificaciones.', '17,7'],
                ['RA05', 'Desarrolla aplicaciones web identificando y aplicando mecanismos para separar el código de presentación de la lógica de negocio.', '17,7'],
                ['RA06', 'Desarrolla aplicaciones web de acceso a almacenes de datos, aplicando medidas para mantener la seguridad y la integridad de la información.', '11,8'],
                ['RA07', 'Desarrolla servicios web reutilizables y accesibles mediante protocolos web, verificando su funcionamiento.', '11,8'],
                ['RA08', 'Genera páginas web dinámicas analizando y utilizando tecnologías y frameworks del servidor web que añadan código al lenguaje de marcas.', '11,8'],
                ['RA09', 'Desarrolla aplicaciones web híbridas seleccionando y utilizando tecnologías, frameworks servidor y repositorios heterogéneos de información.', '11,8'],
            ]
        ),
    ]);

    // T1 / Block 5 — Criterios de evaluación DWES (resumen por RA)
    $blocks[] = $mkBlock($D1, $uFp, $T1B(5), 1, 5, [
        $heading(2, 'Criterios de evaluación'),
        $para('El RD descrito en la Programación de Ciclo indica cuáles deben ser los objetivos específicos de este módulo y lo hace en base a los resultados de aprendizaje a alcanzar, así como los criterios de evaluación que constatarán que se hayan logrado con éxito aquéllos. A continuación, se desglosan los criterios de evaluación por RA y la(s) unidad(es) didáctica(s) en que se trabajan:'),
        $tableAsPara(
            ['RA', 'Criterios de evaluación', 'Unidades didácticas'],
            [
                ['RA1', 'a, b, c, d, e, g (UD1); f (UD2)', 'UD1, UD2'],
                ['RA2', 'a, b, c, d, e, f, g, h', 'UD2'],
                ['RA3', 'a, b, c, d, e, f, g', 'UD2'],
                ['RA4', 'a, b, c, d, e (UD5); f (UD1)', 'UD5, UD1'],
                ['RA5', 'a, b, g (UD3 y UD5); c, d (UD7); e (UD5); f (UD4); h (UD8)', 'UD3, UD4, UD5, UD7, UD8'],
                ['RA6', 'a, b, c, d, e, f (UD4); g (UD8)', 'UD4, UD8'],
                ['RA7', 'a, c, d, e, g (UD6); b, f, h (UD8)', 'UD6, UD8'],
                ['RA8', 'a, b, c, d, e, f, g', 'UD7'],
                ['RA9', 'a, b, c, d, e, f, g, h', 'UD8'],
            ]
        ),
        $para('Nota: algunas unidades podrían tocar aspectos de otros RA, pero la tabla refleja los RA que se evalúan de manera más directa y significativa en cada una.'),
    ]);

    // T1 / Block 6 — Contenidos DWES
    $blocks[] = $mkBlock($D1, $uFp, $T1B(6), 1, 6, [
        $heading(2, 'Contenidos'),
        $para('Los contenidos generales establecidos siguiendo la normativa descrita en la Programación de Ciclo, están divididos en bloques curriculares. A continuación, se detallan los contenidos curriculares del módulo según la normativa.'),
        $heading(3, 'Bloque 1. Selección de arquitecturas y herramientas de programación'),
        $bullet('Modelos de ejecución de código en entornos cliente/servidor.'),
        $bullet('Generación dinámica de páginas web.'),
        $bullet('Lenguajes de programación y tecnologías asociadas en entorno servidor.'),
        $bullet('Integración con los lenguajes de marcas.'),
        $bullet('Integración con los servidores web.'),
        $bullet('Herramientas y frameworks de programación en entorno servidor.'),
        $heading(3, 'Bloque 2. Inserción de código en páginas web'),
        $bullet('Tecnologías asociadas.'),
        $bullet('Obtención del lenguaje de marcas para mostrar en el cliente.'),
        $bullet('Etiquetas para inserción de código.'),
        $bullet('Tipos de datos. Conversiones entre tipos de datos.'),
        $bullet('Variables. Operadores. Ámbitos de utilización.'),
        $bullet('Constructores.'),
        $bullet('Destrucción de objetos y liberación de memoria.'),
        $heading(3, 'Bloque 3. Programación basada en lenguajes de marcas con código embebido'),
        $bullet('Tomas de decisión. Bucles. Matrices (arrays). Tipos de datos compuestos.'),
        $bullet('Funciones.'),
        $bullet('Recuperación y utilización de información proveniente del cliente web.'),
        $bullet('Procesamiento de la información introducida en un formulario.'),
        $bullet('Comentarios.'),
        $heading(3, 'Bloque 4. Desarrollo de aplicaciones web utilizando código embebido'),
        $bullet('Mantenimiento del estado.'),
        $bullet('Almacenamiento y recuperación de información en el cliente web.'),
        $bullet('Seguridad: usuarios, perfiles, roles. Autentificación de usuarios.'),
        $bullet('Pruebas y depuración.'),
        $heading(3, 'Bloque 5. Generación dinámica de páginas web'),
        $bullet('Mecanismos de separación de la lógica de negocio. Frameworks web servidor.'),
        $bullet('Controles de servidor.'),
        $bullet('Mecanismos de generación dinámica de la interface web.'),
        $bullet('Programación orientada a objetos. Patrones de diseño.'),
        $bullet('Prueba y documentación del código.'),
        $heading(3, 'Bloque 6. Utilización de técnicas de acceso a datos'),
        $bullet('Establecimiento de conexiones.'),
        $bullet('Recuperación y edición de información.'),
        $bullet('Utilización de conjuntos de resultados.'),
        $bullet('Actualización y eliminación de información proveniente de una base de datos.'),
        $bullet('Utilización de otros orígenes de datos.'),
        $bullet('Prueba y documentación.'),
        $heading(3, 'Bloque 7. Programación de servicios web'),
        $bullet('Tecnologías y protocolos implicados.'),
        $bullet('Estándares y arquitecturas actuales. Formatos de intercambio de datos.'),
        $bullet('Generación de un servicio web. Interface de un servicio web.'),
        $bullet('Consumo de un servicio web. Herramientas de prueba.'),
        $bullet('Frameworks de documentación.'),
        $heading(3, 'Bloque 8. Generación dinámica de páginas web interactivas'),
        $bullet('Tecnologías y frameworks. Generación dinámica de páginas interactivas.'),
        $bullet('Obtención remota de información.'),
        $bullet('Modificación de la estructura y contenido de la página web.'),
        $heading(3, 'Bloque 9. Desarrollo de aplicaciones web híbridas'),
        $bullet('Tecnologías y frameworks. Reutilización de código e información.'),
        $bullet('Utilización de información proveniente de repositorios.'),
        $bullet('Incorporación de funcionalidades específicas.'),
        $bullet('Utilización de librerías de código relacionadas con Big Data e inteligencia de negocios. Extracción, proceso y análisis de datos provenientes de repositorios.'),
        $bullet('Prueba, depuración y documentación.'),
    ]);

    // T1 / Block 7 — Unidades didácticas DWES
    $blocks[] = $mkBlock($D1, $uFp, $T1B(7), 1, 7, [
        $heading(2, 'Unidades didácticas'),
        $heading(3, 'Listado de unidades'),
        $tableAsPara(
            ['UD', 'Título'],
            [
                ['UD1', 'Arquitectura Web y Entorno de Desarrollo Profesional'],
                ['UD2', 'Fundamentos de PHP y Blade'],
                ['UD3', 'Arquitectura de Software y Patrones de Diseño'],
                ['UD4', 'Persistencia de Datos con Eloquent ORM'],
                ['UD5', 'Autenticación, Autorización y Lógica de Negocio'],
                ['UD6', 'Construcción y Documentación de Servicios Web (API RESTful)'],
                ['UD7', 'Interfaces Dinámicas y Aplicaciones Híbridas'],
                ['UD8', 'Calidad, Rendimiento y Despliegue a Producción'],
            ]
        ),
        $heading(3, 'Relación entre unidades didácticas y bloques de contenidos'),
        $tableAsPara(
            ['UD', 'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9'],
            [
                ['UD1', 'X', 'X', '', '', '', '', '', '', ''],
                ['UD2', '', 'X', 'X', '', '', '', '', '', ''],
                ['UD3', '', '', 'X', '', 'X', '', '', '', ''],
                ['UD4', '', '', '', 'X', '', 'X', '', '', ''],
                ['UD5', '', '', '', 'X', '', '', '', '', ''],
                ['UD6', '', '', '', '', '', '', 'X', '', ''],
                ['UD7', '', '', '', '', '', '', '', 'X', ''],
                ['UD8', '', '', '', '', '', '', '', '', 'X'],
            ]
        ),
        $heading(3, 'Relación entre unidades didácticas y resultados de aprendizaje'),
        $tableAsPara(
            ['UD', 'RA1', 'RA2', 'RA3', 'RA4', 'RA5', 'RA6', 'RA7', 'RA8', 'RA9'],
            [
                ['UD1', 'X', 'X', '', 'X', '', '', '', '', ''],
                ['UD2', '', 'X', 'X', '', '', '', '', '', ''],
                ['UD3', '', '', '', '', 'X', '', '', '', ''],
                ['UD4', '', '', '', '', 'X', 'X', '', '', ''],
                ['UD5', '', '', '', 'X', 'X', '', '', '', ''],
                ['UD6', '', '', '', '', '', '', 'X', '', ''],
                ['UD7', '', '', '', '', 'X', '', '', 'X', 'X'],
                ['UD8', '', '', '', '', 'X', 'X', 'X', '', 'X'],
            ]
        ),
        $heading(3, 'Distribución temporal de las unidades didácticas'),
        $para('La temporalización de las unidades didácticas es una aproximación.'),
        $tableAsPara(
            ['Cuatrimestre', 'Semana', 'Unidad didáctica', 'Nº semanas'],
            [
                ['PRIMERO', '08-09-25', 'Presentación del módulo', '1'],
                ['PRIMERO', '15-09-25', 'UD1', '2'],
                ['PRIMERO', '29-09-25', 'UD2', '3'],
                ['PRIMERO', '20-10-25', 'UD3', '2'],
                ['PRIMERO', '03-11-25', 'UD4', '2'],
                ['PRIMERO', '17-11-25', 'UD4 — No hay TCs', '1'],
                ['SEGUNDO', '24-11-25', 'UD5', '2'],
                ['SEGUNDO', '08-12-25', 'UD6', '2'],
                ['SEGUNDO', '05-01-26', 'UD7', '2'],
                ['SEGUNDO', '19-01-26', 'UD8', '2'],
                ['SEGUNDO', '02-02-26', 'Prueba de validación 2ª evaluación', '1'],
                ['CONVOCATORIA ORDINARIA', '23-02-26', 'Examen', '1'],
                ['SEGUNDA CONVOCATORIA', '04-05-26', 'Examen', '1'],
            ]
        ),
    ]);

    // T1 / Block 9 — Evaluación DWES (modifiable)
    $blocks[] = $mkBlock($D1, $uFp, $T1B(9), 1, 9, [
        $heading(2, 'Evaluación'),
        $heading(3, 'Características de la evaluación'),
        $para('La calificación se realiza mediante Resultados de Aprendizaje (RA). Los RA dualizados en empresa se imparten en el curso, pero su calificación depende de la formación en empresa, con una calificación de "superado" o "no superado". Es imprescindible superar estos RA para aprobar el módulo. Los RA no dualizados tendrán una nota de 0 a 10, con 2 decimales, que será usada para la media final del curso.'),
        $para('Es necesario aprobar todos los RA calificados durante el curso independientemente (5,00 mínimo en cada uno) para superar el módulo. En caso contrario, la nota final será como máximo un 4. Todas las notas menores a un 5 se truncan, es decir, un 4,8 es un 4. En cada evidencia de aprendizaje se obtiene una nota para uno o varios RA evaluados. Las notas de las evaluaciones son meramente informativas y se calculan sobre la base de los RA calificados durante las mismas.'),
        $para('No se repetirá ningún examen o evidencia de aprendizaje, ni se admitirán entregas fuera de plazo salvo causas de fuerza mayor. En caso de sospecha de fraude o copia, la evidencia será calificada con 0 hasta que el alumno defienda su autoría; confirmado el fraude, perderá el derecho a realizar cualquier otra prueba de ese tipo.'),
        $heading(3, 'Tipos de evaluación'),
        $tableAsPara(
            ['EVALUACIÓN', 'TIPO', 'DESDE', 'HASTA', 'EXAMEN'],
            [
                ['CONTINUA', 'EVALUACIÓN 1', '17-11-25', '21-11-25', 'Prácticas Evaluables'],
                ['CONTINUA', 'EVALUACIÓN 2', '02-02-26', '06-02-26', 'Presencial'],
                ['ORDINARIA', 'FINAL', '23-02-26', '27-02-26', 'Presencial'],
                ['SEGUNDA CONVOCATORIA', 'FINAL', '04-05-26', '08-05-26', 'Presencial'],
            ]
        ),
        $heading(3, 'Criterios de calificación (ponderación de RA)'),
        $tableAsPara(
            ['RA', '% sobre módulo', 'Instrumento principal'],
            [
                ['RA1', '5,8', 'Evidencia + Empresa'],
                ['RA2', '5,8', 'Evidencia + Empresa'],
                ['RA3', '5,8', 'Evidencia de aprendizaje'],
                ['RA4', '17,7', 'Evidencia + Empresa'],
                ['RA5', '17,7', 'Evidencia de aprendizaje'],
                ['RA6', '11,8', 'Evidencia + Empresa'],
                ['RA7', '11,8', 'Evidencia + Empresa'],
                ['RA8', '11,8', 'Evidencia + Empresa'],
                ['RA9', '11,8', 'Evidencia + Empresa'],
            ]
        ),
        $heading(3, 'Convocatorias'),
        $para('Tanto la evaluación ordinaria como la segunda convocatoria consisten en un examen presencial donde el alumnado puede presentarse para superar los RA no superados previamente. Estos exámenes pueden ser de RA concretos (si ya se han superado algunos) o de todos los RA del curso. Las notas de los RA calificados en evaluación ordinaria sustituyen a las de la evaluación continua en caso de existir. Las notas de los RA calificados en evaluación extraordinaria sustituyen cualquier nota anterior.'),
        $para('Si se desea mejorar la nota de RA aprobados, el alumnado deberá renunciar a todas las calificaciones obtenidas previamente y presentarse de todos los RA. En caso de no presentarse en la convocatoria ordinaria, la calificación que aparecerá en el boletín será la obtenida según la evaluación continua.'),
        $para('Cálculo de la nota final del módulo (en ordinaria y extraordinaria): N_F = N_F_RA1 + N_F_RA2 + … + N_F_RA9. Si todos los N_F_RA >= 5: la nota final es la MEDIA. En caso contrario: MÍNIMO entre 4 y MEDIA.'),
    ]);

    // T1 / Block 10 — Actividades didácticas complementarias DWES (optional, sin contenido específico)
    $blocks[] = $mkBlock($D1, $uFp, $T1B(10), 1, 10, [
        $heading(2, 'Actividades didácticas complementarias'),
        $para('No se contemplan actividades extraescolares en este módulo, aunque a lo largo del curso se indicarán a los alumnos jornadas (p. ej., jornadas de empleabilidad, jornadas de talento), cursos y seminarios que puedan servir de interés para el desarrollo profesional.'),
    ]);

    // ============================================================
    // D2 — IPO I (T1) — bloques: 1, 2, 3, 4, 5, 6, 7, 9, 10
    // Idioma: valenciano
    // ============================================================

    // T1 / Block 1 — Cabecera IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(1), 2, 1, [
        $heading(1, "Itinerari Personal per a l'Ocupabilitat I (1709)"),
        $paraBold("Cicle formatiu: Desenvolupament d'Aplicacions Web (1r CFGS)"),
        $paraBold('Hores totals: 96 hores (3 hores/setmana)'),
        $paraBold('Professorat: Sonia Durà Bou — Departament de FOL'),
    ]);

    // T1 / Block 2 — Introducció IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(2), 2, 2, [
        $heading(2, 'Introducció'),
        $heading(3, 'Justificació de la programació'),
        $para("La present programació està concebuda per al mòdul Itinerari personal per a l'ocupabilitat I, impartit en el primer curs d'aquest cicle formatiu al CEEDCV en València. A partir de la justificació i la contextualització, s'integraran les diferents parts que la componen, amb l'objectiu que esdevinga un document útil i eficaç per a la pràctica docent."),
        $para("Programar és un procés de presa de decisions mitjançant el qual es defineix la intenció educativa d'una manera organitzada i precisa. Així, la programació constitueix un instrument de planificació de l'activitat a l'aula."),
        $para('Aquesta programació es caracteritza per:'),
        $bullet("L'adequació al context, tenint en compte les característiques pròpies del sector productiu corresponent al títol."),
        $bullet("La concreció, ja que s'ajusta a l'estructura establida en l'Ordre d'inici de curs."),
        $bullet('La viabilitat, en adaptar-se al temps i als recursos disponibles.'),
        $bullet("La flexibilitat, en ser revisada sempre que es detecten incidències, amb la finalitat d'introduir els ajustos oportuns que garantisquen la millora contínua del procés i permeten atendre les necessitats de l'alumnat."),
        $heading(3, 'Ubicació del mòdul'),
        $tableAsPara(
            ['Element', 'Valor'],
            [
                ["Mòdul Professional", "Itinerari Personal per a l'Ocupabilitat I"],
                ['CFGS', "Desenrotllament d'Aplicacions Web"],
                ['Família Professional', 'Informàtica i Comunicacions'],
                ['Duració del cicle', '2000 hores'],
                ['Duració del mòdul', '96 hores (3 hores setmanals)'],
                ['Professorat', "Professorat d'educació secundària"],
            ]
        ),
    ]);

    // T1 / Block 3 — Competències IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(3), 2, 3, [
        $heading(2, 'Competències professionals, personals i socials'),
        $para("El perfil professional del títol queda determinat per la seua competència general, les seues competències professionals, personals i socials, i per la relació de qualificacions i unitats de competència del Catàleg Nacional de Qualificacions Professionals incloses en el títol."),
        $para("Una competència és la capacitat de posar en pràctica de forma integrada aquells coneixements adquirits, aptituds i trets de personalitat que permeten resoldre situacions diverses. El concepte de competència va més enllà del «saber» i el «saber fer» ja que inclou el «saber ser» i el «saber estar»."),
        $para("En l'article 5 del RD de títol s'estableixen les competències professionals, personals i socials. Les que es treballen en el mòdul d'IPO I són les següents:"),
        $bullet("Adaptar-se a les noves situacions laborals, mantenint actualitzats els coneixements científics, tècnics i tecnològics relatius al seu entorn professional, gestionant la seua formació i els recursos existents en l'aprenentatge al llarg de la vida."),
        $bullet("Resoldre situacions, problemes o contingències amb iniciativa i autonomia en l'àmbit de la seua competència, amb creativitat, innovació i esperit de millora en el treball personal i en el dels membres de l'equip."),
        $bullet("Organitzar i coordinar equips de treball amb responsabilitat, supervisant el desenvolupament d'aquest, mantenint relacions fluides i assumint el lideratge, així com aportant solucions als conflictes grupals que es presenten."),
        $bullet("Comunicar-se amb els seus iguals, superiors, clients i persones sota la seua responsabilitat, utilitzant vies eficaces de comunicació, transmetent la informació o coneixements adequats i respectant l'autonomia i competència de les persones que intervenen en l'àmbit del seu treball."),
        $bullet("Generar entorns segurs en el desenvolupament del seu treball i el del seu equip, supervisant i aplicant els procediments de prevenció de riscos laborals i ambientals, d'acord amb el que s'estableix per la normativa i els objectius de l'empresa."),
        $bullet("Exercir els seus drets i complir amb les obligacions derivades de la seua activitat professional, d'acord amb el que s'estableix en la legislació vigent, participant activament en la vida econòmica, social i cultural."),
    ]);

    // T1 / Block 4 — Resultats d'aprenentatge IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(4), 2, 4, [
        $heading(2, "Resultats d'aprenentatge"),
        $para("Els resultats d'aprenentatge (RA) per unitat didàctica són:"),
        $tableAsPara(
            ['Codi', "Resultat d'aprenentatge", '%'],
            [
                ['R1', "Arriba a les competències necessàries per a l'obtenció del Títol de tècnic bàsic en Prevenció de Riscos Laborals.", '25%'],
                ['R2', 'Distingeix les característiques del sector productiu i defineix els llocs de treball relacionant-los amb les competències professionals expressades al títol.', '5%'],
                ['R3', 'Analitza les seues condicions laborals com a persona treballadora per compte aliè, identificant-les en els principals tipus de contractes, canvis i vicissituds rellevants que es poden presentar a la relació laboral, a la normativa laboral i especialment al conveni col·lectiu del sector.', '30%'],
                ['R4', "Analitza i avalua el seu potencial professional i els seus interessos per orientar-se en el procés d'autoorientació, i elabora una fulla de ruta per a la inserció professional basada en l'anàlisi de les competències, interessos i destreses personals.", '15%'],
                ['R5', "Aplica les estratègies per a l'aprenentatge autònom reconeixent-ne el valor professionalitzador, dissenyant i optimitzant el seu propi entorn d'aprenentatge, fent ús de les tecnologies digitals com a eines d'aprenentatge autònom.", '20%'],
                ['R6', "Identifica el concepte de salut psicosocial derivada de l'exercici professional, identificant i avaluant els factors de risc associats, i aplicant les mesures correctores corresponents.", '5%'],
                ['', 'TOTAL', '100%'],
            ]
        ),
    ]);

    // T1 / Block 5 — Criteris d'avaluació IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(5), 2, 5, [
        $heading(2, "Criteris d'avaluació"),
        $para("Relacionem els RA amb els criteris d'avaluació, els continguts i les unitats de treball associades:"),
        $tableAsPara(
            ['RA', "Criteris d'avaluació (resum)", 'UT', '%'],
            [
                ['R1', "Cultura preventiva, factors de risc, danys derivats (accidents, malalties), avaluació de riscos, protocols d'emergència, drets i deures preventius, gestió de la prevenció, vigilància de la salut i primers auxilis.", '1, 2, 3 i 4', '25%'],
                ['R2', "Oportunitats d'ocupació i inserció laboral al sector, comparativa de requeriments del mercat laboral i la funció pública, actituds i aptituds requerides per al sector.", '10', '5%'],
                ['R3', "Drets i obligacions de la relació laboral, conveni col·lectiu, modalitats de contractació, components del rebut de salari, recursos laborals, Seguretat Social, prestacions per suspensió i extinció.", '5, 6, 7, 8 i 9', '30%'],
                ['R4', "Autoconeixement, competències personals i socials per a l'ocupació, projecte professional, autoestima, DAFO personal, itineraris formatius, objectius i pla d'acció.", '12', '15%'],
                ['R5', "Responsabilitat individual en el desenvolupament professional, ocupabilitat, entorn personal d'aprenentatge, competència digital, identitat digital, pla de desenvolupament individual i eines d'aprenentatge autònom.", '11', '20%'],
                ['R6', "Salut psicosocial al treball, sinistralitat i absentisme, factors de risc psicosocial, danys i impacte, estrès laboral, tecnoestrès i burnout, estratègies d'afrontament i mesures d'intervenció.", '2', '5%'],
            ]
        ),
    ]);

    // T1 / Block 6 — Continguts IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(6), 2, 6, [
        $heading(2, 'Continguts'),
        $para("Els continguts constitueixen el conjunt de sabers, habilitats i formes culturals que s'organitzen i es desenvolupen a través de les activitats d'ensenyament i aprenentatge a l'aula. Han d'estar vinculats i adequadament alineats amb els resultats d'aprenentatge (RA), que actuen com a eix vertebrador i guia del procés formatiu."),
        $heading(3, 'R1 — Salut laboral i prevenció (UT 1, 2, 3 i 4)'),
        $bullet('Conceptes bàsics sobre seguretat i salut a la feina. El treball i la salut: riscos professionals. Factors de risc.'),
        $bullet('Danys derivats del treball: accidents i malalties professionals. Marc normatiu bàsic. Drets i deures.'),
        $bullet('Riscos generals i prevenció: riscos lligats a condicions de seguretat, al medi ambient de treball, càrrega de treball, fatiga i insatisfacció.'),
        $bullet('Sistemes elementals de control de riscos. Protecció col·lectiva i individual. Plans d’emergència i evacuació.'),
        $bullet('Control de la salut dels treballadors. Riscos específics del sector. Elements bàsics de gestió de la prevenció. Primers auxilis.'),
        $heading(3, 'R2 — Sector productiu (UT 10)'),
        $bullet("Cerca, selecció i maneig d'informació acadèmica i professional. Presa de decisions. Definició d'objectius professionals."),
        $bullet("Aprenentatge autònom i competència digital. Entorn personal d'aprenentatge. Marca personal. Identitat digital i ocupabilitat."),
        $heading(3, 'R3 — Condicions laborals (UT 5, 6, 7, 8 i 9)'),
        $bullet('Drets i deures derivats de la relació laboral. Contracte de treball: elements bàsics i modalitats.'),
        $bullet('Components del rebut de salari. Negociació col·lectiva i mesures de conflicte. Conveni col·lectiu.'),
        $bullet("Seguretat Social i estat del benestar. Prestacions i tràmits per suspensió i extinció del contracte. Incapacitat temporal."),
        $heading(3, 'R4 — Autoorientació professional (UT 12)'),
        $bullet('Autoanàlisi i presa de decisions acadèmiques i professionals. Autoconeixement: interessos, competències, habilitats i motivacions.'),
        $bullet("Anàlisi i avaluació del potencial professional. DAFO personal. Orientació per a la igualtat d'oportunitats."),
        $heading(3, 'R5 — Aprenentatge autònom (UT 11)'),
        $bullet("Reptes laborals derivats de l'àmbit digital. Sector productiu i perfil professional. Anàlisi del mercat de treball."),
        $bullet("Concepte d'ocupabilitat. Àrees ocupacionals. Anàlisi de lloc de treball. Identitat digital i impacte a l'ocupabilitat."),
        $heading(3, 'R6 — Salut psicosocial (UT 2)'),
        $bullet("Salut psicosocial lligada a l'àmbit laboral. Sinistralitat i absentisme. Definició i classificació de riscos psicosocials."),
        $bullet("Estrès laboral, tecnoestrès i burnout. Temps de treball. Desconnexió digital. Conciliació personal i laboral. Factors de protecció."),
    ]);

    // T1 / Block 7 — Unitats didàctiques IPO I
    $blocks[] = $mkBlock($D2, $uFp, $T1B(7), 2, 7, [
        $heading(2, 'Unitats didàctiques'),
        $heading(3, 'Organització i seqüenciació'),
        $tableAsPara(
            ['UD', 'Continguts', 'Quadrimestre', 'Setmanes'],
            [
                ['1', 'La salut laboral', '1', '2'],
                ['2', 'Factors de risc', '1', '4'],
                ['3', 'Planificació i gestió de la prevenció', '1', '2'],
                ['4', 'Evacuació i primers auxilis', '1', '2'],
                ['5', 'El Dret del treball', '2', '1'],
                ['6', 'El contracte de treball', '2', '1'],
                ['7', 'El salari i la nòmina', '2', '2'],
                ['8', 'Modificació, suspensió i extinció del contracte', '2', '1'],
                ['9', 'Seguretat Social', '2', '1'],
                ['10', 'Anàlisi del sector professional', '2', '2'],
                ['11', "L'aprenentatge autònom i la competència digital", 'Dualitzat empresa', '1'],
                ['12', 'El full de ruta per a la inserció professional', '1', '1'],
            ]
        ),
        $heading(3, 'Distribució temporal'),
        $tableAsPara(
            ['UD', "Data d'inici", 'Data final', 'Data límit tasques'],
            [
                ['Presentació del mòdul', '22/09/25', '26/09/25', ''],
                ['UD 1', '29/09/25', '10/10/25', '19/10/25'],
                ['UD 2', '13/10/25', '07/11/25', '16/11/25'],
                ['UD 3', '10/11/25', '21/11/25', '30/11/25'],
                ['UD 4', '24/11/25', '05/12/25', '14/12/25'],
                ['Repàs', '08/12/25', '09/01/26', ''],
                ['Examen 1r quadrimestre online', '12/01/26', '16/01/26', ''],
                ['UD 5', '19/01/26', '23/01/26', '01/02/26'],
                ['UD 6', '26/01/26', '30/01/26', '08/02/26'],
                ['UD 7', '02/02/26', '13/02/26', '22/02/26'],
                ['UD 8', '16/02/26', '20/02/26', '01/03/26'],
                ['UD 9', '23/02/26', '27/02/26', '08/03/26'],
                ['UD 10', '02/03/26', '13/03/26', '22/03/26'],
                ['UD 11', '23/03/26', '02/04/26', 'Dualització en empresa'],
                ['UD 12', '', '', 'Dualització en empresa'],
                ['Repàs', '14/04/26', '04/05/26', ''],
                ['Examen 2n quadrimestre presencial', '05/05/26', '09/05/26', ''],
                ['Convocatòria ordinària', '18/05/26', '22/05/26', ''],
                ['Convocatòria segona ordinària', '15/06/26', '19/06/26', ''],
            ]
        ),
    ]);

    // T1 / Block 9 — Avaluació IPO I (modifiable)
    $blocks[] = $mkBlock($D2, $uFp, $T1B(9), 2, 9, [
        $heading(2, 'Avaluació'),
        $heading(3, "Característiques de l'avaluació"),
        $para("L'avaluació dels alumnes serà CRITERIAL: es realitzarà segons els criteris d'avaluació establerts per als resultats d'aprenentatge del mòdul. Per superar el mòdul s'hauran de superar tots els resultats d'aprenentatge compresos en aquest, ja que l'article 18 del Reial Decret 659/2023 estableix que l'avaluació verifiqui l'adquisició dels resultats d'aprenentatge en les condicions de qualitat establertes."),
        $para("Per aconseguir aprovar el RA del mòdul d'IPO I es farà una mitjana dels diferents CE que formen aquest RA tenint en compte les ponderacions de cada CE."),
        $para("L'alumnat pot optar entre dues modalitats d'avaluació: avaluació no contínua (presentació directa a la convocatòria ordinària o segona ordinària) i avaluació contínua (per tasques, recomanada per al seguiment i aprofitament del mòdul)."),
        $heading(3, "Procediments i instruments d'avaluació"),
        $bullet("Pràctica a l'aula: ordre i neteja, vocabulari específic, organització, interpretació de resultats, ortografia, lliurament en termini. Rúbrica."),
        $bullet('Prova escrita o prova de validació sobre continguts teoricopràctics. Escala numèrica.'),
        $bullet('Activitats: participació, intervenció i aportació a la dinàmica diària de classe; rutines de pensament; qüestionaris oberts; treballs escrits; pràctiques d’autoavaluació; desenvolupament d’un projecte o repte.'),
        $heading(3, 'Criteris de qualificació'),
        $tableAsPara(
            ['RA', '% de la qualificació'],
            [
                ['R1', '25%'],
                ['R2', '5%'],
                ['R3', '30%'],
                ['R4', '15%'],
                ['R5', '20%'],
                ['R6', '5%'],
                ['TOTAL', '100%'],
            ]
        ),
        $para('La nota final del mòdul es determinarà amb la següent ponderació:'),
        $tableAsPara(
            ['Component', '% de la nota'],
            [
                ['Tasques', '30%'],
                ['Participació en fòrums', '5%'],
                ['Examen o prova escrita', '65%'],
            ]
        ),
        $heading(3, "Modalitats d'avaluació"),
        $para("Si s'opta per l'avaluació contínua: en la convocatòria ordinària, l'alumnat només haurà de presentar-se a la prova dels RA no superats; en la segona convocatòria ordinària, haurà d'examinar-se de tots els resultats d'aprenentatge impartits al centre."),
        $para("Si s'opta per l'avaluació no contínua: l'alumnat haurà de realitzar un examen global sobre tots els continguts del curs en Aules, tant en la convocatòria ordinària com en la segona ordinària. Les dates oficials de les proves seran les establertes pel calendari del CEEDCV i no es podran modificar."),
        $heading(3, 'Condicions especials i recuperació'),
        $bullet("Coincidència >50% entre tasques: convocatòria per aportar aclariments; si no es justifica, les tasques quedaran invalidades."),
        $bullet('Qualsevol indici de còpia o plagi en proves de convocatòria ordinària/extraordinària implicarà el suspens automàtic del mòdul per a tots els implicats.'),
        $bullet("Inactivitat: alumnat sense connexió 10 dies consecutius passarà a estat inactiu; es comunicarà com a baixa d'activitat si no respon."),
        $bullet("Abans de cada convocatòria s'organitzaran sessions de repàs i tutories col·lectives."),
    ]);

    // T1 / Block 10 — Activitats didàctiques complementàries IPO I (optional, amb contingut real)
    $blocks[] = $mkBlock($D2, $uFp, $T1B(10), 2, 10, [
        $heading(2, 'Activitats didàctiques complementàries'),
        $para("Les activitats didàctiques complementàries tenen com a finalitat enriquir el procés d'ensenyament-aprenentatge, afavorint l'aplicació pràctica dels continguts del mòdul i el desenvolupament de les competències professionals, personals i socials. Es vinculen als tres grans blocs temàtics del mòdul."),
        $heading(3, 'Bloc de Salut Laboral'),
        $bullet("Estudi de casos reals: anàlisi d'accidents laborals greus, identificant causes, errades de prevenció i mesures correctores."),
        $bullet('Visionat de vídeos formatius sobre prevenció de riscos laborals relacionats amb el sector professional.'),
        $bullet("Avaluació de riscos laborals: estudi dels llocs de treball del sector per elaborar una avaluació i un pla de prevenció propi."),
        $heading(3, 'Bloc de Legislació i Salut Laboral'),
        $bullet("Estudi comparatiu de legislacions laborals: anàlisi de marcs normatius per comprendre variacions i similituds."),
        $bullet("Visionat i anàlisi de seqüències de la pel·lícula «Treball Fem», sobre condicions laborals i reptes dels treballadors."),
        $bullet("Visionat del documental «Sicko» de Michael Moore: comparativa Seguretat Social espanyola i sistema dels EUA."),
        $heading(3, "Bloc d'Orientació i Inserció Laboral"),
        $bullet("Xarrada o trobada d'orientació professional (fòrum, tutoria síncrona o sessió presencial) dedicada a la cerca d'ocupació i a la definició d'itineraris acadèmics i professionals."),
        $heading(3, 'Activitats transversals'),
        $bullet("Visionat del documental «Fashion Victims»: vulneració de drets laborals a la indústria tèxtil."),
        $bullet("Visionat de conferències de Víctor Küppers i Sergio Ayala sobre actitud positiva i humor com a competència professional."),
        $heading(3, 'Activitats complementàries presencials'),
        $para("Des del Departament de FOL es proposa, sempre que siga viable i compatible amb les activitats dels altres mòduls, la visita a la Ciutat de la Justícia de València. Aquesta activitat permet conèixer de prop el funcionament de les institucions judicials i la seua relació amb el món laboral."),
    ]);

    // ============================================================
    // D3 — LAP (T1) — bloques: 1, 2, 3, 4, 5, 6, 7, 9, 10
    // ============================================================

    // T1 / Block 1 — Cabecera LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(1), 3, 1, [
        $heading(1, 'Logística de Aprovisionamiento (0626)'),
        $paraBold('Ciclo formativo: Transporte y Logística (2º CFGS — Comercio y Marketing)'),
        $paraBold('Horas totales: 100 horas (3 horas/semana)'),
        $paraBold('Profesorado: Elena Ortega Pradillas'),
    ]);

    // T1 / Block 2 — Introducción LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(2), 3, 2, [
        $heading(2, 'Introducción'),
        $heading(3, 'Justificación de la programación'),
        $para('El módulo de Logística de aprovisionamiento (LAP) se incluye en el segundo curso del ciclo formativo de grado superior de Transporte y Logística, con una carga lectiva de 3 horas semanales y 100 horas anuales en la modalidad de enseñanza presencial. La carga lectiva expuesta se corresponde con las horas de clase, a las que tenemos que añadir las horas dedicadas al estudio personal y a realizar actividades, lo que deberemos tener en cuenta a la hora de planificar el módulo. El resto de normativa por el cual se regula esta programación queda recogida en la programación de ciclo.'),
    ]);

    // T1 / Block 3 — Competencias LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(3), 3, 3, [
        $heading(2, 'Competencias profesionales, personales y sociales'),
        $heading(3, 'Competencia general'),
        $para('Siguiendo el artículo 4 del RD 1572/2011, de 4 de noviembre, la competencia general de este título consiste en "gestionar las operaciones comerciales de compraventa y distribución de productos y servicios, y organizar la implantación y animación de espacios comerciales según criterios de calidad, seguridad y prevención de riesgos", aplicando la normativa vigente.'),
        $heading(3, 'Competencias profesionales, personales y sociales del módulo'),
        $tableAsPara(
            ['Código', 'Competencia'],
            [
                ['I', 'Realizar y controlar el aprovisionamiento de materiales y mercancías en los planes de producción y de distribución, asegurando la cantidad, calidad, lugar y plazos para cumplir con los objetivos establecidos por la organización y/o clientes.'],
                ['L', 'Adaptarse a las nuevas situaciones laborales, manteniendo actualizados los conocimientos científicos, técnicos y tecnológicos relativos a su entorno profesional, gestionando su formación y los recursos existentes en el aprendizaje a lo largo de la vida.'],
                ['M', 'Resolver situaciones, problemas o contingencias con iniciativa y autonomía en el ámbito de su competencia.'],
                ['N', 'Organizar y coordinar equipos de trabajo con responsabilidad, supervisando el desarrollo del mismo, manteniendo relaciones fluidas y asumiendo el liderazgo.'],
                ['Ñ', 'Comunicarse con sus iguales, superiores, clientes y personas bajo su responsabilidad, utilizando vías eficaces de comunicación.'],
                ['O', 'Generar entornos seguros en el desarrollo de su trabajo y el de su equipo, supervisando y aplicando los procedimientos de prevención de riesgos laborales y ambientales.'],
                ['P', 'Supervisar y aplicar procedimientos de gestión de calidad, de accesibilidad universal y de «diseño para todos» en las actividades profesionales.'],
                ['Q', 'Realizar la gestión básica para la creación y funcionamiento de una pequeña empresa y tener iniciativa en su actividad profesional con sentido de la responsabilidad social.'],
                ['R', 'Ejercer sus derechos y cumplir con las obligaciones derivadas de su actividad profesional, de acuerdo con la legislación vigente, participando activamente en la vida económica, social y cultural.'],
            ]
        ),
        $heading(3, 'Unidades de Competencia'),
        $bullet('UC1003_3: Colaborar en la elaboración del plan de aprovisionamiento.'),
        $bullet('UC1004_3: Realizar el seguimiento y control del programa de aprovisionamiento.'),
    ]);

    // T1 / Block 4 — Resultados de aprendizaje LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(4), 3, 4, [
        $heading(2, 'Resultados de aprendizaje'),
        $para('El RD 1572/2011 indica cuáles deben ser los objetivos específicos de este módulo y lo hace en base a los resultados del aprendizaje a alcanzar por el alumnado. A continuación, se desglosan los resultados de aprendizaje junto al porcentaje del criterio de calificación que se le asigna:'),
        $tableAsPara(
            ['RA', 'Descripción', '% calificación'],
            [
                ['RA1', 'Determina las necesidades de materiales y plazos para la ejecución de programas de producción y distribución, siguiendo los planes definidos.', '20%'],
                ['RA2', 'Elabora programas de aprovisionamiento, ajustándose a objetivos, plazos y criterios de calidad de los procesos de producción/distribución.', '10%'],
                ['RA3', 'Aplica métodos de gestión de stocks, realizando previsiones de requerimientos de mercancías y materiales en sistemas de producción y aprovisionamiento.', '25%'],
                ['RA4', 'Realiza la selección, el seguimiento y la evaluación de los proveedores, aplicando los mecanismos de control, seguridad y calidad del proceso y del programa de aprovisionamiento.', '10%'],
                ['RA5', 'Determina las condiciones de negociación del aprovisionamiento, aplicando técnicas de comunicación y negociación con proveedores.', '25%'],
                ['RA6', 'Elabora la documentación relativa al control, registro e intercambio de información con proveedores, siguiendo los procedimientos de calidad y utilizando aplicaciones informáticas.', '10%'],
            ]
        ),
        $para('El resultado de aprendizaje 3 se activa para el módulo de Proyecto Intermodular II.'),
    ]);

    // T1 / Block 5 — Criterios de evaluación LAP (resumen)
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(5), 3, 5, [
        $heading(2, 'Criterios de evaluación'),
        $para('Los criterios de evaluación se ponderan dentro de cada RA con los porcentajes establecidos en la normativa. Se reflejan a continuación en forma resumida los criterios principales por RA:'),
        $heading(3, 'RA1 (20%) — Necesidades de materiales y plazos'),
        $bullet('a) Caracterizar procesos de producción (duración, gama, productos). (5%)'),
        $bullet('b) Relacionar previsión de demanda con producción/distribución, gestión de stocks e inventario. (5%)'),
        $bullet('c) Evaluar enfoques de gestión del aprovisionamiento en la cadena. (5%)'),
        $bullet('d) Representar el proceso mediante esquemas de flujo (mercancías e información). (25%)'),
        $bullet('e) Determinar capacidades productivas y tiempos de fase. (10%)'),
        $bullet('f) Aplicar técnicas de planificación de la producción y distribución. (20%)'),
        $bullet('g) Identificar cuellos de botella. (15%)'),
        $bullet('h) Establecer puntos críticos y soluciones. (15%)'),
        $heading(3, 'RA2 (10%) — Programas de aprovisionamiento'),
        $bullet('a) Secuenciar fases del programa de aprovisionamiento. (25%)'),
        $bullet('b) Calcular el coste del programa. (5%)'),
        $bullet('c) Definir programa de pedidos y entregas. (5%)'),
        $bullet('d) Elaborar diagramas de flujo de operaciones. (25%)'),
        $bullet('e) Planificar cantidades y fechas. (15%)'),
        $bullet('f) Elaborar el calendario. (15%)'),
        $bullet('g) Usar programas informáticos. (10%)'),
        $heading(3, 'RA3 (25%) — Gestión de stocks'),
        $bullet('a) Evaluar consecuencias económicas de la integración de la gestión de stocks. (10%)'),
        $bullet('b) Relacionar procedimientos de gestión y control con tipos de existencias. (10%)'),
        $bullet('c) Clasificar productos almacenados por distintos métodos. (15%)'),
        $bullet('d) Evaluar incidencias en valoración, control de inventario y rupturas. (15%)'),
        $bullet('e) Calcular estimaciones de volumen de existencias. (15%)'),
        $bullet('f) Determinar punto y lote de pedido óptimos. (15%)'),
        $bullet('g) Calcular stock de seguridad y su coste. (10%)'),
        $bullet('h) Evaluar costes de demanda insatisfecha. (10%)'),
        $heading(3, 'RA4 (10%) — Selección y evaluación de proveedores'),
        $bullet('a) Definir criterios esenciales en la selección y establecer pliego de condiciones. (15%)'),
        $bullet('b) Establecer baremo de criterios, clasificar y priorizar ofertas. (15%)'),
        $bullet('c) Buscar proveedores potenciales online y offline. (15%)'),
        $bullet('d) Analizar calidad, plazos y precios. (10%)'),
        $bullet('e) Evaluar recursos del proveedor (técnicos, personal, financieros). (10%)'),
        $bullet('f) Analizar cumplimiento estimado de condiciones. (10%)'),
        $bullet('g) Analizar restricciones logísticas (nacional e internacional). (15%)'),
        $bullet('h) Redactar informes de evaluación de proveedores. (10%)'),
        $heading(3, 'RA5 (25%) — Condiciones de negociación (evaluado en empresa)'),
        $bullet('a) Identificar fases del proceso de negociación. (25%)'),
        $bullet('b) Aplicar técnicas de comunicación y negociación. (25%)'),
        $bullet('c) Diferenciar tipos de contratos. (5%)'),
        $bullet('d) Identificar elementos del contrato de suministro. (10%)'),
        $bullet('e) Aplicar normativa mercantil de contratos. (5%)'),
        $bullet('f) Establecer cláusulas del contrato. (10%)'),
        $bullet('g) Usar aplicaciones de tratamiento de textos para redacción de contrato. (20%)'),
        $heading(3, 'RA6 (10%) — Documentación e intercambio con proveedores (evaluado en empresa)'),
        $bullet('a) Establecer proceso de control de pedidos. (20%)'),
        $bullet('b) Definir medidas de resolución de anomalías. (20%)'),
        $bullet('c) Definir sistema de recogida y tratamiento de datos. (20%)'),
        $bullet('d) Cumplimentar documentos internos. (5%)'),
        $bullet('e) Cumplimentar documentos de intercambio con proveedores. (10%)'),
        $bullet('f) Determinar tipo de información a manejar. (10%)'),
        $bullet('g) Usar base de datos centralizada. (5%)'),
        $bullet('h) Establecer mecanismos de fiabilidad e integridad de datos. (10%)'),
    ]);

    // T1 / Block 6 — Contenidos LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(6), 3, 6, [
        $heading(2, 'Contenidos'),
        $para('Los contenidos generales establecidos en el RD 1572/2011, de 4 de noviembre, y ampliados en la Orden 39/2015, de 31 de marzo, están divididos en seis bloques curriculares relacionados directamente con cada uno de los resultados de aprendizaje.'),
        $heading(3, 'Bloque 1 — Políticas de aprovisionamiento y organización de la producción (RA1)'),
        $bullet('Logística: definición, orígenes, componentes, tipos y condicionantes. Previsión de demanda y plan de ventas. Plan de producción y de materiales.'),
        $bullet('Características de los procesos (duración, gama, productos). Programación de la producción. Producción por lotes. Estructura del producto.'),
        $bullet('Planificación de necesidades de materiales (MRP) y de distribución (DRP). Enfoques (JIT, Kanban, otros).'),
        $bullet('Programación y control de proyectos: PERT, CPM, GANTT. Definición de actividades, cálculo de tiempos, holguras y cuellos de botella.'),
        $heading(3, 'Bloque 2 — Variables y planes de aprovisionamiento (RA2)'),
        $bullet('Variables que influyen: previsión de demanda, volumen de pedido, precio, plazo de aprovisionamiento, plazo de pago.'),
        $bullet('Aprovisionamiento continuo y periódico. Previsión de necesidades. Plan de compras. Programación de pedidos.'),
        $bullet('Aplicaciones informáticas en la planificación del aprovisionamiento.'),
        $heading(3, 'Bloque 3 — Gestión de stocks (RA3)'),
        $bullet('Tipología de compras y ciclo de aprovisionamiento.'),
        $bullet('Gestión de inventarios. Clases de inventario. Costes: de capital, servicio, mantenimiento, riesgo, pedido, rotura.'),
        $bullet('ABC de inventarios. Métodos de gestión: push/pull, EOQ, stock de seguridad, punto de pedido, revisión continua y periódica.'),
        $bullet('Hoja de cálculo: fórmulas, gráficos, listas, filtros, macros.'),
        $heading(3, 'Bloque 4 — Selección y evaluación de proveedores (RA4)'),
        $bullet('Homologación de proveedores. Cuestionarios, auditoría, muestras, certificación.'),
        $bullet('Proveedores potenciales y activos. Criterios de selección. AHP. Fuentes de información.'),
        $bullet('Análisis de ofertas: precios, costes, TCO. Evaluación de proveedores (instalaciones, procesos, calidad, capacidad financiera, etc.).'),
        $bullet('Gestión del riesgo. Mercado internacional de suministros. Compra electrónica y subastas. Externalización y subcontratación.'),
        $heading(3, 'Bloque 5 — Negociación y contratos (RA5)'),
        $bullet('Proceso de negociación de las compras. Preparación, puntos críticos, técnicas. Relación proveedor-cliente. Decálogo del comprador.'),
        $bullet('Contrato de compraventa/suministro: tipos, elementos, normativa mercantil, cláusulas, redacción. Aplicaciones de tratamiento de textos.'),
        $heading(3, 'Bloque 6 — Documentación y control de proveedores (RA6)'),
        $bullet('Proceso de aprovisionamiento. Diagrama de flujo de documentación. Verificación de cumplimiento de cláusulas.'),
        $bullet('Órdenes de pedido/entrega. Recepción, identificación y verificación. Seguimiento del pedido. Control de salidas.'),
        $bullet('Aplicaciones informáticas de gestión y seguimiento. Bases de datos. Registro y valoración de proveedores.'),
        $heading(3, 'Contenidos actitudinales'),
        $bullet('Conciencia de la importancia de la empresa en la sociedad actual. Sensibilidad y solidaridad ante problemas socioeconómicos.'),
        $bullet('Valoración de la labor del empresario para el progreso social. Atención a los cambios del entorno y avances tecnológicos.'),
        $bullet('Búsqueda de la excelencia profesional. Reflexión sobre las repercusiones de las decisiones empresariales. Desarrollo sostenible.'),
        $bullet('Iniciativa, responsabilidad e identidad profesional. Interés y responsabilidad en el puesto.'),
    ]);

    // T1 / Block 7 — Unidades didácticas LAP
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(7), 3, 7, [
        $heading(2, 'Unidades didácticas'),
        $heading(3, 'Relación entre unidades de trabajo y resultados de aprendizaje'),
        $tableAsPara(
            ['UD', 'Título', 'RA01', 'RA02', 'RA03', 'RA04', 'RA05', 'RA06', 'Horas'],
            [
                ['UD1', 'Cadena de suministro', 'X', '', '', '', '', '', '10'],
                ['UD2', 'La función de producción', 'X', '', '', '', '', '', '10'],
                ['UD3', 'Criterios de selección de los proveedores', '', 'X', '', 'X', '', 'X', '10'],
                ['UD4', 'Negociación con proveedores', '', '', '', '', 'X', 'X', '15'],
                ['UD5', 'Sistema de producción. Mejora continua', '', '', 'X', '', '', '', '15'],
                ['UD6', 'Aplicaciones de ordenador para logística', '', '', '', '', '', 'X', '10'],
                ['UD7', 'Gestión de inventario', '', '', 'X', '', '', '', '25'],
            ]
        ),
        $heading(3, 'Distribución temporal de las unidades didácticas'),
        $para('La temporalización es una aproximación.'),
        $tableAsPara(
            ['Cuatrimestre', 'UD', 'Contenido', 'Fechas'],
            [
                ['PRIMERO', 'UD0', 'Presentación del módulo', '15-21 septiembre'],
                ['PRIMERO', 'UD1', 'Cadena de suministro', '22 sep — 5 oct'],
                ['PRIMERO', 'UD2', 'La función de producción', '6-17 octubre'],
                ['PRIMERO', 'UD3', 'Criterios de selección de proveedores', '20-31 octubre'],
                ['PRIMERO', '—', 'Consolidación de contenidos', '10-14 noviembre'],
                ['PRIMERO', '—', 'Primera evaluación', '17-20 noviembre'],
                ['SEGUNDO', 'UD4', 'Negociación con proveedores', '3-28 noviembre'],
                ['SEGUNDO', 'UD5', 'Aplicaciones de ordenador para logística', '1-12 diciembre'],
                ['SEGUNDO', 'UD6', 'Gestión de inventario', '15 dic — 9 enero'],
                ['SEGUNDO', 'UD7', 'Sistema de producción. Mejora continua', '12-23 enero'],
                ['SEGUNDO', '—', 'Consolidación de contenidos', '26-30 enero'],
                ['SEGUNDO', '—', 'Segunda evaluación', '2-6 febrero'],
            ]
        ),
    ]);

    // T1 / Block 9 — Evaluación LAP (modifiable)
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(9), 3, 9, [
        $heading(2, 'Evaluación'),
        $heading(3, 'Características de la evaluación'),
        $para('La evaluación se realizará en base a los Resultados de Aprendizaje y Criterios de Evaluación. Para que se considere apto el módulo es necesario que todos los RA estén superados. La nota de cada RA se calculará como media ponderada de cada uno de los CE que lo componen. La nota del módulo se calculará como la media ponderada de cada uno de los RA del módulo.'),
        $para('Tal y como se establece en la nueva ley de FP el módulo ha sido dualizado y por tanto habrá Criterios de Evaluación que se evalúen por parte de la empresa en el periodo de formación en empresa. La aprobación definitiva de los RA, y del módulo, quedará supeditada a la efectiva superación del periodo de formación en empresa. En LAP los RA dualizados son el RA5 y el RA6.'),
        $heading(3, 'Tipos de evaluación'),
        $tableAsPara(
            ['EVALUACIÓN', 'TIPO', 'DESDE', 'HASTA', 'EXAMEN'],
            [
                ['CONTINUA', 'EVALUACIÓN 1', '17/11', '21/11', 'Online'],
                ['CONTINUA', 'EVALUACIÓN 2', '02/02', '06/02', 'Presencial'],
                ['ORDINARIA', 'FINAL', '23/02', '27/02', 'Presencial'],
                ['SEGUNDA CONVOCATORIA', 'FINAL', '04/05', '08/05', 'Presencial'],
            ]
        ),
        $heading(3, 'Criterios de calificación (evaluación continua)'),
        $tableAsPara(
            ['Componente', '% de la nota'],
            [
                ['Tareas evaluables', '20%'],
                ['Examen de la evaluación', '80%'],
            ]
        ),
        $para('Para realizar el examen de la evaluación se deberán haber entregado todas las tareas en tiempo y forma (una tarea entregada fuera de plazo será calificada con 0 puntos), obtener una calificación mínima de 5 sobre 10 en el examen del cuatrimestre y 5 sobre 10 en la media de las tareas. En el caso de que algún RA no se haya superado, independientemente de que la media ponderada sea superior a 5, la evaluación no estará aprobada.'),
        $para('La nota final del curso será la media aritmética de ambas evaluaciones siempre que se hayan superado todos los resultados de aprendizaje, con calificación mínima de 5 en el total y en cada RA.'),
        $heading(3, 'Convocatoria ordinaria y extraordinaria'),
        $para('Si el alumnado ha aprobado las tareas pero no el examen de la evaluación, podrá presentarse a la convocatoria ordinaria con la materia correspondiente a la evaluación suspendida. El examen será presencial y supondrá el 100% de la nota, con dos partes (teórica y práctica). Para aprobar es necesario obtener al menos 5/10 y que todos los RA estén aprobados.'),
        $para('En caso de no superar la ordinaria, el alumnado tendrá derecho a la segunda convocatoria sobre la totalidad de la materia, con las mismas características.'),
        $heading(3, 'Protocolo de exámenes online'),
        $bullet('Debe quedar visible el espacio de trabajo (mesa, teclado, material) y la pantalla del ordenador.'),
        $bullet('Permanecer conectado en todo momento desde el inicio del examen hasta su finalización (o autorización de la profesora).'),
        $bullet('Sin vista correcta, el alumnado será expulsado y tendrá la evaluación suspendida; lo mismo si pierde la conexión o se desconecta antes de la finalización.'),
        $bullet('No se mueven las fechas excepto casos de fuerza mayor estipulados en el reglamento del CEEDCV.'),
        $heading(3, 'Plagio y copia'),
        $bullet('Coincidencia superior al 50% entre tareas: convocatoria al alumnado para aclaraciones; si no se esclarecen los hechos, todas las tareas implicadas quedan invalidadas.'),
        $bullet('Plagio: suspenso de la tarea en cuestión (0). Reincidencia: suspenso del módulo completo.'),
    ]);

    // T1 / Block 10 — Actividades complementarias LAP (optional)
    $blocks[] = $mkBlock($D3, $uJefeEFp, $T1B(10), 3, 10, [
        $heading(2, 'Actividades didácticas complementarias'),
        $para('No se contemplan actividades extraescolares en este módulo, aunque a lo largo del curso se indicarán a los alumnos jornadas (p.ej., jornadas de empleabilidad, jornadas de talento), cursos y seminarios que puedan servir de interés para su desarrollo profesional.'),
    ]);

    // ============================================================
    // D4 — PXSI1 (T2) — bloques: 1, 2, 4, 5, 7
    // (3 contextualización, 6 aula virtual, 8 medidas inclusión = locked)
    // ============================================================

    // T2 / Block 1 — Cabecera PXSI1
    $blocks[] = $mkBlock($D4, $uBach, $T2B(1), 4, 1, [
        $heading(1, 'Programación, Redes y Sistemas Informáticos I'),
        $paraBold('Nivel: 1º de Bachillerato — Modalidad: Ciencias y Tecnología'),
        $paraBold('Departamento: Informática — Tipo: Troncal de modalidad / Específica'),
        $paraBold('Profesorado: Javier Llorens Anduix'),
    ]);

    // T2 / Block 2 — Introducción y justificación PXSI1
    $blocks[] = $mkBlock($D4, $uBach, $T2B(2), 4, 2, [
        $heading(2, 'Introducción y justificación'),
        $para('El desarrollo de los avances tecnológicos y digitales está marcando la evolución de la sociedad del s. XXI. Es notorio cómo afectan a la vida cotidiana estos cambios y el ritmo con los que se producen, lo que justifica la necesidad de dotar al alumnado de capacidad de adaptación satisfactoria. La materia Programación, Redes y Sistemas Informáticos aborda el pensamiento computacional, los sistemas informáticos, las redes, y los servicios en red desde un punto de vista crítico, responsable y solidario para hacer frente a los principales retos de una sociedad digitalizada.'),
        $para('Esta materia favorece la consecución de los objetivos de Bachillerato gracias a su desarrollo práctico, colaborativo y crítico, lo que facilita el crecimiento personal y académico del alumnado. La realización en grupo de proyectos informáticos y de programación ayuda a fortalecer la confianza en sí mismo del alumnado, la iniciativa personal, la autonomía, la creatividad, la flexibilidad y el sentido estético, así como la capacidad de planificar, tomar decisiones y asumir responsabilidades proactivamente en el trabajo diario.'),
        $para('El currículo de esta materia responde a los principios pedagógicos de la LOMLOE, ya que las situaciones de aprendizaje planteadas contemplan las diferentes capacidades del alumnado y promueven el trabajo en equipo, el aprendizaje autónomo y la aplicación de métodos de investigación adecuados. La materia tiene una dimensión eminentemente práctica, abordada a través de la búsqueda de soluciones técnicas a desafíos derivados de una sociedad cada vez más digitalizada.'),
        $para('Los aprendizajes esenciales se concretan en cinco competencias específicas, cuatro bloques de saberes básicos (programación, sistemas informáticos, redes y servicios en red) y los correspondientes criterios de evaluación. Las cuatro primeras competencias están directamente relacionadas con cada uno de los cuatro grupos de saberes, mientras que la última competencia aborda, desde una perspectiva integradora, los retos de una sociedad digitalizada.'),
        $heading(3, 'Marco normativo específico de la asignatura'),
        $bullet('DECRETO 108/2022, de 5 de agosto, del Consell, por el que se establecen la ordenación y el currículo de Bachillerato.'),
        $bullet('REAL DECRETO 243/2022, de 5 de abril, por el que se establecen la ordenación y las enseñanzas mínimas del Bachillerato.'),
        $bullet('Decreto 107/2022, de 5 de agosto, del Consell, por el que se establecen la ordenación y el currículo de Educación Secundaria Obligatoria.'),
        $bullet('ORDEN 19/2023, de 29 de junio, de la Conselleria de Educación, Cultura y Deporte, por la que se regulan los procedimientos derivados del Decreto 107/2022 y del Decreto 108/2022.'),
        $bullet('RESOLUCIÓN de 24 de octubre de 2022, de la Dirección General de Centros Docentes, sobre cursar determinadas materias de modalidad de Bachillerato en el CEED.'),
        $bullet('LEY ORGÁNICA 3/2020, de 29 de diciembre (LOMLOE) y LEY ORGÁNICA 2/2006, de 3 de mayo (LOE).'),
        $bullet('Real Decreto 205/2023, de 28 de marzo, sobre la transición entre planes de estudios.'),
    ]);

    // T2 / Block 4 — Situaciones de aprendizaje PXSI1
    $blocks[] = $mkBlock($D4, $uBach, $T2B(4), 4, 4, [
        $heading(2, 'Situaciones de aprendizaje y criterios de evaluación asociados'),
        $para('Con el objetivo de conferir un enfoque competencial a la materia, los saberes confluyen en proyectos que suponen situaciones de aprendizaje contextualizadas, en las que el alumnado aplica sus conocimientos y destrezas para dar solución a una necesidad concreta. Los saberes básicos se integran en situaciones de aprendizaje contextualizadas, que permitan el desarrollo de las competencias específicas asociadas a los criterios de evaluación.'),
        $heading(3, 'Situación de aprendizaje 1 — Hardware'),
        $para('Descripción y justificación: El dominio de la función y de las características de los componentes internos de un ordenador permite diagnosticar la causa de un mal funcionamiento y facilita su mantenimiento. Además, ayuda a tomar decisiones fundamentadas al actualizar o comprar un nuevo equipo.'),
        $bullet('Competencias específicas vinculadas: BL2.1, BL2.2, BL2.3, BL2.4, BL2.5, BL2.6.'),
        $bullet('Saberes básicos (Bloque 2 Sistemas informáticos / CE2 y CE5): unidades de medida y representación digital, arquitectura del ordenador, criterios de selección de componentes, simuladores de hardware, interacción de componentes, dispositivos móviles.'),
        $bullet('Organización y temporalización: 4 sesiones (función y características de los sistemas informáticos; adecuación a necesidades).'),
        $heading(3, 'Situación de aprendizaje 2 — Sistemas Operativos'),
        $para('Descripción y justificación: Es importante conocer los diferentes sistemas operativos para elegir adecuadamente el que instalaremos en nuestro ordenador, maximizando rendimiento y eficiencia.'),
        $bullet('Competencias específicas: BL2.1 a BL2.7 (incluye uso seguro y saludable en dispositivos, redes y servicios en red).'),
        $bullet('Saberes básicos: identificación de elementos de un programa, propiedad intelectual y licencias, industria del software, sistemas operativos (PC y dispositivos móviles), instalación y configuración de SO y aplicaciones, implicaciones para el bienestar digital y la sostenibilidad.'),
        $bullet('Organización y temporalización: 4 sesiones — necesidad del SO, tipos (Windows, MacOS, Linux, Android), partes (kernel, drivers, UI), funciones (memoria, archivos, seguridad, procesos), evolución, ventajas/desventajas, instalación y uso práctico.'),
        $heading(3, 'Situación de aprendizaje 3 — Redes de ordenadores'),
        $para('Descripción y justificación: Comprender cómo se comunican y comparten información los equipos permite garantizar la seguridad de la red.'),
        $bullet('Competencias específicas: BL3.2 a BL3.7 (analizar diseño de red, configurar y conectar de forma segura, ciudadanía digital crítica, uso seguro de dispositivos y servicios).'),
        $bullet('Saberes básicos (Bloque 3 Redes / CE3 y CE5): orígenes y evolución, tipos de red, modelos y protocolos, dispositivos y medios, direccionamiento físico y lógico, diseño e instalación, seguridad cableada e inalámbrica, cifrado, routers, monitorización.'),
        $bullet('Organización y temporalización: 4 sesiones — tipos de redes (LAN, WAN, MAN, WLAN), cableadas/inalámbricas, TCP/IP, DNS, DHCP, VPN, diseño LAN, IP y máscaras, administración, aplicaciones en red.'),
        $heading(3, 'Situación de aprendizaje 4 — Bases de datos y WordPress'),
        $para('Descripción y justificación: Gestionar grandes cantidades de información de manera eficiente implica dominar el manejo de las bases de datos. Además, utilizaremos esas bases de datos en sistemas de gestión de contenidos como WordPress.'),
        $bullet('Competencias específicas: BL1.1, BL1.2, BL1.3, BL2.1-2.4, BL4.4, BL4.5, BL4.6, BL4.7, BL4.8.'),
        $bullet('Saberes básicos: propiedad intelectual y sesgos del software; servidor web, instalación y configuración; gestores de contenidos; bases de datos en local y red; certificado y firma digital; gestión de identidad y huella digital; ciberconvivencia, privacidad y protección de datos.'),
        $bullet('Organización y temporalización: 3 sesiones — tipos de BD, SQL, diseño (modelo Entidad-Relación), administración, integración con APIs y aplicaciones, aplicación práctica en la vida cotidiana y empresarial.'),
        $heading(3, 'Situación de aprendizaje 5 — Programación'),
        $para('Descripción y justificación: Es fundamental dominar los algoritmos antes de empezar a programar. Permiten entender el proceso lógico detrás de la solución de un problema y la escritura de código claro, eficiente y escalable.'),
        $bullet('Competencias específicas: BL1.1 a BL1.6, BL2.1, BL2.2.'),
        $bullet('Saberes básicos: representación de problemas, abstracción y secuenciación, paradigmas, lenguajes compilados/interpretados, elementos del programa (variables, tipos, operadores, estructuras de control, funciones), BBDD (consultas, inserciones, modificación), ciclo de vida (análisis, diseño, codificación, pruebas, documentación, mantenimiento), entornos de desarrollo, depuración y validación, propiedad intelectual y sesgos.'),
        $bullet('Organización y temporalización: 9 sesiones — lenguajes, compiladores e intérpretes, diagramas de flujo y pseudocódigo, estructura básica de datos, toma de decisiones, funciones.'),
        $para('Instrumentos de evaluación comunes a todas las situaciones: puntualidad y participación (observables); rúbricas, lista de comprobación y realización de proyectos individuales o en conjunto (prácticos); exámenes teóricos mediante cuestionarios y tareas (conocimientos).'),
    ]);

    // T2 / Block 5 — Temporalización PXSI1
    $blocks[] = $mkBlock($D4, $uBach, $T2B(5), 4, 5, [
        $heading(2, 'Temporalización'),
        $tableAsPara(
            ['UD', 'Contenido', 'Fecha', 'Nº semanas'],
            [
                ['UD0', 'Presentación', '15-19/9', '1'],
                ['UD1', 'Sistemas informáticos — Hardware', '22/9 — 22/10', '4'],
                ['UD2', 'Sistemas informáticos — Software', '28/10 — 24/11', '4'],
                ['UD3', 'Redes', '25/11 — 12/12', '3'],
                ['—', 'EXAMEN PRIMERA EVALUACIÓN', '15-19/12', '1'],
                ['UD4', 'Sistemas de Gestión de Contenidos y Bases de datos', '07/01 — 30/01', '3'],
                ['UD5', 'Programación / Introducción', '01/02 — 23/02', '3'],
                ['UD6', 'Programación / Operaciones', '24/02 — 15/03', '3'],
                ['UD7', 'Programación / Ciclos de vida y herramientas', '17/03 — 13/04', '3'],
                ['—', 'EXAMEN DE LA SEGUNDA EVALUACIÓN', '20-24/04', '1'],
                ['—', 'REPASO', '23-27/02', '1'],
                ['—', 'CONVOCATORIA EXAMEN ORDINARIA', '11-15/05', '1'],
                ['—', 'CONVOCATORIA EXAMEN EXTRAORDINARIA', '15-19/06', '1'],
            ]
        ),
    ]);

    // T2 / Block 7 — Evaluación PXSI1 (modifiable)
    $blocks[] = $mkBlock($D4, $uBach, $T2B(7), 4, 7, [
        $heading(2, 'Evaluación'),
        $para('La evaluación continua se entiende como un elemento inherente al proceso de enseñanza-aprendizaje. No se pretende valorar solamente el conocimiento conceptual de los alumnos, sino también sus habilidades en contextos reales de uso. Es fundamental incardinar la evaluación en el proceso mismo de aprendizaje, siendo las principales actividades de enseñanza-aprendizaje al mismo tiempo actividades de evaluación.'),
        $heading(3, 'Criterios de evaluación de las competencias específicas'),
        $para('Conforme el Real Decreto 243/2022, de 5 de abril, artículo 20, la evaluación del aprendizaje será continua y diferenciada según las distintas materias. El profesorado decidirá al término del curso si el alumno o alumna ha logrado los objetivos y ha alcanzado el adecuado grado de adquisición de las competencias. Se promueve el uso generalizado de instrumentos de evaluación variados, diversos, flexibles y adaptados a las distintas situaciones de aprendizaje.'),
        $heading(3, 'Valoración del proceso de aprendizaje — instrumentos'),
        $bullet('Formato de los archivos entregados, conforme se especifique en la actividad.'),
        $bullet('Calidad del resultado entregado (uso de métodos y técnicas explicadas, resultado esperado).'),
        $bullet('Interés por ampliar conocimientos y mejorar técnicas de trabajo.'),
        $bullet('Trabajo en equipo y ayuda a compañeros (donde proceda).'),
        $bullet('Uso adecuado del Aula Virtual (Aules): entrega en plazo, formato adecuado. Entre 1 y 5 días de retraso: 50% de la puntuación; más de 5 días: equivalente a no entregada. Copia: 0 para todos los implicados.'),
        $heading(3, 'Tareas y exámenes'),
        $bullet('Actividades autoevaluables: recomendables tras el estudio para comprobar el nivel.'),
        $bullet('Actividades evaluables: obligatorias, representan el 40% de la nota de cada evaluación. Se entregan en el Aula Virtual con fecha límite específica. Nota: media de todas las actividades.'),
        $bullet('Exámenes: dos evaluaciones a lo largo del curso. Teórico-prácticos por unidades, pudiendo agruparse varias. Mayoritariamente prácticos, online en uno de los turnos (mañana/tarde). El profesor se reserva el derecho a hacer examen presencial. Es necesario haber entregado todas las actividades obligatorias para presentarse al examen.'),
        $heading(3, 'Criterios de calificación cuantitativa'),
        $tableAsPara(
            ['Componente', '% de la nota'],
            [
                ['Exámenes', '60%'],
                ['Tareas', '40%'],
            ]
        ),
        $para('Resultados de evaluación: números sin decimales de 1 a 10 (Sobresaliente 9-10, Notable 7-8, Bien 6, Suficiente 5, Insuficiente 1-4). Si la nota media final es inferior a 5, alguna nota de evaluación inferior a 4, alguna nota de exámenes menor a 4 o la media de las prácticas de alguna de las evaluaciones menor a 3, el alumnado deberá recuperar dichas evaluaciones en la prueba ordinaria. La nota final será 50% primera evaluación y 50% la segunda evaluación.'),
        $heading(3, 'Convocatorias ordinaria y extraordinaria'),
        $bullet('Convocatoria ordinaria: el examen supondrá el 100% de la nota; mínimo para aprobar 5/10. Las actividades realizadas durante el curso no se tendrán en cuenta para esta prueba.'),
        $bullet('Convocatoria extraordinaria: examinarse de toda la asignatura; mínimo para aprobar 5/10. Mismas condiciones que la ordinaria.'),
        $bullet('Copia o uso de IA en exámenes: suspenso de la evaluación continua y obligación de recuperar todo el temario en la convocatoria ordinaria.'),
        $heading(3, 'Requisitos para hacer los exámenes'),
        $bullet('Documento acreditativo de la identidad (DNI, pasaporte, NIE o carnet de conducir).'),
        $bullet('Carnet del centro o, en su defecto, hoja de matrícula.'),
        $bullet('Ordenador y cámara o móvil.'),
        $bullet('Conocer las condiciones de realización del examen (especialmente si es online); disponibles en el aula de la materia días antes.'),
    ]);

    return $blocks;
})();
