<?php

declare(strict_types=1);

return [
    'document' => [
        'validation_requested' => [
            'title' => 'New review request',
            'body' => 'The document ":document_title" requires your review',
        ],
        'ownership_transferred' => [
            'title' => 'A document has been assigned to you',
            'body' => ':actor_name has assigned you the document ":document_title"',
        ],
    ],

    'template' => [
        'validation_requested' => [
            'title' => 'New template review request',
            'body' => 'The template ":template_name" requires your review',
        ],
        'ownership_transferred' => [
            'title' => 'A template has been assigned to you',
            'body' => ':actor_name has assigned you the template ":template_name"',
        ],
    ],
];
