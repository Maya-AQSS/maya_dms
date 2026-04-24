<?php

declare(strict_types=1);

/**
 * Contenido BlockNote (lista de nodos) para la plantilla global publicada DWES (seed).
 *
 * @return list<array<string, mixed>>
 */
return (static function (): array {
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

    return [
        $heading(2, '1. Identificación y Contextualización'),
        $para(
            'Ciclo Formativo: Grado Superior en Desarrollo de Aplicaciones Web (DAW). '
            .'Módulo: Desarrollo Web en Entorno Servidor. Código: 0612. '
            .'Duración total: 160 - 180 horas (según Comunidad Autónoma). Curso: 2º.'
        ),
        $heading(2, '2. Objetivos Generales y Competencias'),
        $para(
            'El objetivo es que el alumno sea capaz de configurar servidores, gestionar bases de datos '
            .'y programar la lógica de negocio de una aplicación web de forma segura.'
        ),
        $para(
            'Competencias clave: Gestionar bases de datos mediante lenguajes de programación. '
            .'Desarrollar interfaces de usuario dinámicas. Asegurar el acceso a los datos y la privacidad de la información.'
        ),
        $heading(2, '3. Unidades Didácticas (Cronograma sugerido)'),
        $para(
            'Unidad | Título | Contenidos clave | Duración (horas)'
            ."\n".'UT 1 | Instalación y Configuración | Servidores locales (XAMPP/Docker), sintaxis básica de PHP. | 15h'
            ."\n".'UT 2 | Generación dinámica de páginas | Formularios, validación de datos y manejo de estados. | 20h'
            ."\n".'UT 3 | Acceso a Datos (BBDD) | Conectores (PDO/MySQLi), sentencias preparadas y CRUD. | 35h'
            ."\n".'UT 4 | Programación Orientada a Objetos | Clases, herencia, interfaces y namespaces en servidor. | 25h'
            ."\n".'UT 5 | Arquitectura Web (MVC) | Separación de lógica y vista, uso de motores de plantillas. | 25h'
            ."\n".'UT 6 | Servicios Web y API REST | Consumo y creación de APIs, formato JSON y autenticación. | 30h'
            ."\n".'UT 7 | Frameworks y Seguridad | Introducción a Laravel/Symfony, cifrado y sesiones. | 30h'
        ),
        $heading(2, '4. Metodología'),
        $para(
            'Se aplicará un enfoque práctico y orientado a proyectos (ABP): explicación teórica corta con conceptos '
            .'clave y ejemplos en vivo (live coding); retos diarios para fijar la sintaxis; proyecto final que integre '
            .'todas las unidades (por ejemplo un e-commerce o una red social educativa).'
        ),
        $heading(2, '5. Evaluación'),
        $para(
            'Evaluación continua basada en resultados de aprendizaje (RA). Pruebas prácticas (exámenes): 40%. '
            .'Proyectos o prácticas de unidad: 50% (calidad del código, seguridad y funcionalidad). '
            .'Actitud y participación: 10% (uso de Git, puntualidad en entregas). '
            .'Para aprobar el módulo suele ser obligatorio tener aprobadas todas las unidades con una nota mínima de 5.'
        ),
        $heading(2, '6. Recursos Necesarios'),
        $para(
            'Software: Visual Studio Code, Docker/XAMPP, Git/GitHub, Postman, MySQL Workbench. '
            .'Hardware: equipo con mínimo 8 GB de RAM (recomendado 16 GB para contenedores). '
            .'Plataforma: Moodle o Google Classroom para la gestión de materiales.'
        ),
    ];
})();
