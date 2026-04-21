<?php

/**
 * Catálogo mock de jerarquía académica para tablas *_source (entorno local).
 * Orden de inserción: study_types_source → studies_source → course_modules_source.
 */
return [
    'study_types_source' => [
        ['id' => 'ST_ESPA', 'name' => 'Educación Secundaria para Personas Adultas'],
        ['id' => 'ST_BACH', 'name' => 'Bachillerato'],
        ['id' => 'ST_FP', 'name' => 'Formación Profesional'],
    ],
    'studies_source' => [
        ['id' => 'S_ESPA', 'study_type_id' => 'ST_ESPA', 'name' => 'Graduado en Educación Secundaria'],
        ['id' => 'S_BACH_1_C', 'study_type_id' => 'ST_BACH', 'name' => '1º Bachillerato Ciencias'],
        ['id' => 'S_BACH_2_C', 'study_type_id' => 'ST_BACH', 'name' => '2º Bachillerato Ciencias'],
        ['id' => 'S_FP_DAW', 'study_type_id' => 'ST_FP', 'name' => 'CFGS Desarrollo de Aplicaciones Web'],
        ['id' => 'S_FP_ASIR', 'study_type_id' => 'ST_FP', 'name' => 'CFGS Administración de Sistemas Informáticos en Red'],
        ['id' => 'S_FP_SMR_1', 'study_type_id' => 'ST_FP', 'name' => '1º CFGM Sistemas Microinformáticos y Redes'],
        ['id' => 'S_FP_SMR_2', 'study_type_id' => 'ST_FP', 'name' => '2º CFGM Sistemas Microinformáticos y Redes'],
    ],
    'course_modules_source' => [
        ['id' => 'M_MAT_1', 'study_id' => 'S_ESPA', 'name' => 'Matemáticas'],
        ['id' => 'M_ENG_1', 'study_id' => 'S_ESPA', 'name' => 'Inglés'],
        ['id' => 'M_LEN_2', 'study_id' => 'S_ESPA', 'name' => 'Lengua castellana y literatura'],
        ['id' => 'M_FIS_1C', 'study_id' => 'S_BACH_1_C', 'name' => 'Física y Química'],
        ['id' => 'M_BIO_2C', 'study_id' => 'S_BACH_2_C', 'name' => 'Biología'],
        ['id' => 'M_DAW_DWECL', 'study_id' => 'S_FP_DAW', 'name' => 'Desarrollo Web en Entorno Cliente'],
        ['id' => 'M_DAW_DWES', 'study_id' => 'S_FP_DAW', 'name' => 'Desarrollo Web en Entorno Servidor'],
        ['id' => 'M_DAW_DIW', 'study_id' => 'S_FP_DAW', 'name' => 'Diseño de interfaces web'],
        ['id' => 'M_ASIR_SRI', 'study_id' => 'S_FP_ASIR', 'name' => 'Implantación de sistemas operativos'],
        ['id' => 'M_ASIR_SAD', 'study_id' => 'S_FP_ASIR', 'name' => 'Administración de sistemas gestores de bases de datos'],
        ['id' => 'M_SMR_MME', 'study_id' => 'S_FP_SMR_1', 'name' => 'Montaje y mantenimiento de equipos'],
        ['id' => 'M_SMR_PAR', 'study_id' => 'S_FP_SMR_2', 'name' => 'Planificación y administración de redes'],
    ],
];
