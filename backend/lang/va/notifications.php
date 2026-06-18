<?php

declare(strict_types=1);

return [
    'document' => [
        'validation_requested' => [
            'title' => 'Nova sol·licitud de revisió',
            'body' => 'El document ":document_title" requerix la teua revisió',
        ],
        'ownership_transferred' => [
            'title' => "T'han cedit un document",
            'body' => ':actor_name t\'ha cedit el document ":document_title"',
        ],
    ],

    'template' => [
        'validation_requested' => [
            'title' => 'Nova sol·licitud de revisió de plantilla',
            'body' => 'La plantilla ":template_name" requerix la teua revisió',
        ],
        'ownership_transferred' => [
            'title' => "T'han cedit una plantilla",
            'body' => ':actor_name t\'ha cedit la plantilla ":template_name"',
        ],
    ],
];
