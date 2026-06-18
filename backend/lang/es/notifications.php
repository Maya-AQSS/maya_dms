<?php

declare(strict_types=1);

return [
    'document' => [
        'validation_requested' => [
            'title' => 'Nueva solicitud de revisión',
            'body' => 'El documento ":document_title" requiere tu revisión',
        ],
        'ownership_transferred' => [
            'title' => 'Te han cedido un documento',
            'body' => ':actor_name te ha cedido el documento ":document_title"',
        ],
    ],

    'template' => [
        'validation_requested' => [
            'title' => 'Nueva solicitud de revisión de plantilla',
            'body' => 'La plantilla ":template_name" requiere tu revisión',
        ],
        'ownership_transferred' => [
            'title' => 'Te han cedido una plantilla',
            'body' => ':actor_name te ha cedido la plantilla ":template_name"',
        ],
    ],
];
