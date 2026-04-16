<?php

/**
 * Catálogo de usuarios mock para entorno local.
 * Campos: id, nombre, email, departamento.
 * La VIEW pública los expone como: id, name, email, department.
 *
 * Idempotente: UsersSourceSeeder usa insertOrIgnore, no duplica al re-seed.
 */
return [
    // ── Usuarios de demo originales ───────────────────────────────────────
    [
        'id'          => 'usr_direction_demo',
        'nombre'      => 'Direccion Demo',
        'email'       => 'direccion.demo@maya.local',
        'departamento' => 'Dirección',
    ],
    [
        'id'          => 'usr_secretariat_demo',
        'nombre'      => 'Secretaria Demo',
        'email'       => 'secretaria.demo@maya.local',
        'departamento' => 'Secretaría',
    ],
    [
        'id'          => 'usr_hierarchy_eso_demo',
        'nombre'      => 'Docente ESO Demo',
        'email'       => 'docente.eso.demo@maya.local',
        'departamento' => 'ST_ESO',
    ],
    [
        'id'          => 'usr_hierarchy_bach_demo',
        'nombre'      => 'Docente Bachillerato Demo',
        'email'       => 'docente.bach.demo@maya.local',
        'departamento' => 'ST_BACH',
    ],
    [
        'id'          => 'usr_hierarchy_fp_demo',
        'nombre'      => 'Docente FP Demo',
        'email'       => 'docente.fp.demo@maya.local',
        'departamento' => 'ST_FP',
    ],

    // ── Usuarios de prueba realistas ──────────────────────────────────────
    [
        'id'          => 'usr_ana_martinez',
        'nombre'      => 'Ana Martínez',
        'email'       => 'ana.martinez@maya.test',
        'departamento' => 'Coordinación',
    ],
    [
        'id'          => 'usr_maria_garcia',
        'nombre'      => 'María García',
        'email'       => 'maria.garcia@maya.test',
        'departamento' => 'Profesorado',
    ],
    [
        'id'          => 'usr_juan_rodriguez',
        'nombre'      => 'Juan Rodríguez',
        'email'       => 'juan.rodriguez@maya.test',
        'departamento' => 'Revisión',
    ],
    [
        'id'          => 'usr_julia_sanchez',
        'nombre'      => 'Julia Sánchez',
        'email'       => 'julia.sanchez@maya.test',
        'departamento' => 'Validación',
    ],
    [
        'id'          => 'usr_carlos_lopez',
        'nombre'      => 'Carlos López',
        'email'       => 'carlos.lopez@maya.test',
        'departamento' => 'Profesorado',
    ],
    [
        'id'          => 'usr_sara_ruiz',
        'nombre'      => 'Sara Ruiz',
        'email'       => 'sara.ruiz@maya.test',
        'departamento' => 'Revisión',
    ],
    [
        'id'          => 'usr_pedro_fernandez',
        'nombre'      => 'Pedro Fernández',
        'email'       => 'pedro.fernandez@maya.test',
        'departamento' => 'Coordinación',
    ],
    [
        'id'          => 'usr_laura_torres',
        'nombre'      => 'Laura Torres',
        'email'       => 'laura.torres@maya.test',
        'departamento' => 'Profesorado',
    ],
    [
        'id'          => 'usr_aura_delgado',
        'nombre'      => 'Aura Delgado',
        'email'       => 'aura.delgado@maya.test',
        'departamento' => 'Validación',
    ],
    [
        'id'          => 'usr_miguel_jimenez',
        'nombre'      => 'Miguel Jiménez',
        'email'       => 'miguel.jimenez@maya.test',
        'departamento' => 'Revisión',
    ],
    [
        'id'          => 'usr_lucia_moreno',
        'nombre'      => 'Lucía Moreno',
        'email'       => 'lucia.moreno@maya.test',
        'departamento' => 'Secretaría',
    ],
    [
        'id'          => 'usr_javier_navarro',
        'nombre'      => 'Javier Navarro',
        'email'       => 'javier.navarro@maya.test',
        'departamento' => 'Dirección',
    ],
    [
        'id'          => 'usr_elena_castro',
        'nombre'      => 'Elena Castro',
        'email'       => 'elena.castro@maya.test',
        'departamento' => 'Coordinación',
    ],
    [
        'id'          => 'usr_alberto_ortiz',
        'nombre'      => 'Alberto Ortiz',
        'email'       => 'alberto.ortiz@maya.test',
        'departamento' => 'Profesorado',
    ],
    [
        'id'          => 'usr_carmen_vega',
        'nombre'      => 'Carmen Vega',
        'email'       => 'carmen.vega@maya.test',
        'departamento' => 'Validación',
    ],
    [
        'id'          => 'usr_andres_molina',
        'nombre'      => 'Andrés Molina',
        'email'       => 'andres.molina@maya.test',
        'departamento' => 'Revisión',
    ],
    [
        'id'          => 'usr_nuria_blanco',
        'nombre'      => 'Nuria Blanco',
        'email'       => 'nuria.blanco@maya.test',
        'departamento' => 'Profesorado',
    ],
    [
        'id'          => 'usr_roberto_aguilar',
        'nombre'      => 'Roberto Aguilar',
        'email'       => 'roberto.aguilar@maya.test',
        'departamento' => 'Coordinación',
    ],
];
