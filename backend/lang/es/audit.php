<?php

declare(strict_types=1);

return [
    // Shared parts (optional suffixes, prepend a space)
    'at_stage' => ' (etapa :stage)',
    'by_reviewer' => ' por :reviewer',
    'unnamed' => 'sin nombre',

    'template' => [
        'state_changed' => [
            'rejected' => 'Plantilla ":name" rechazada:stage_info:by_info',
            'published' => 'Plantilla ":name" publicada:stage_info:by_info',
            'default' => 'Estado de plantilla ":name" cambiado de :old a :new',
        ],
        'review_approved' => 'Etapa :stage de plantilla ":name" aprobada:by_info',
        'submitted_for_review' => 'Plantilla ":name" enviada a revisión con :count validador(es)',
    ],

    'document' => [
        'state_changed' => [
            'rejected' => 'Documento ":title" rechazado:stage_info:by_info',
            'published' => 'Documento ":title" publicado:stage_info:by_info',
            'default' => 'Estado de documento ":title" cambiado de :old a :new',
        ],
        'review_approved' => 'Etapa :stage de documento ":title" aprobada:by_info',
        'submitted_for_review' => 'Documento ":title" enviado a revisión con :count validador(es)',
    ],

    'template_version_block_layer' => [
        'included' => 'Bloque ":block_title" incluido en versión de plantilla',
        'removed' => 'Bloque ":block_title" marcado como eliminado en versión de plantilla',
        'updated' => 'Bloque ":block_title" actualizado en versión de plantilla',
    ],
];
