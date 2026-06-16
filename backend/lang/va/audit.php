<?php

declare(strict_types=1);

return [
    'at_stage' => ' (etapa :stage)',
    'by_reviewer' => ' per :reviewer',
    'unnamed' => 'sense nom',

    'template' => [
        'state_changed' => [
            'rejected' => 'Plantilla ":name" rebutjada:stage_info:by_info',
            'published' => 'Plantilla ":name" publicada:stage_info:by_info',
            'default' => 'Estat de plantilla ":name" canviat de :old a :new',
        ],
        'review_approved' => 'Etapa :stage de plantilla ":name" aprovada:by_info',
        'submitted_for_review' => 'Plantilla ":name" enviada a revisió amb :count validador(s)',
    ],

    'document' => [
        'state_changed' => [
            'rejected' => 'Document ":title" rebutjat:stage_info:by_info',
            'published' => 'Document ":title" publicat:stage_info:by_info',
            'default' => 'Estat de document ":title" canviat de :old a :new',
        ],
        'review_approved' => 'Etapa :stage de document ":title" aprovada:by_info',
        'submitted_for_review' => 'Document ":title" enviat a revisió amb :count validador(s)',
    ],

    'template_version_block_layer' => [
        'included' => 'Bloc ":block_title" inclòs en versió de plantilla',
        'removed' => 'Bloc ":block_title" marcat com a eliminat en versió de plantilla',
        'updated' => 'Bloc ":block_title" actualitzat en versió de plantilla',
    ],
];
