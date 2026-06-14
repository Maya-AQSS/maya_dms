<?php

return [
    'document' => [
        'delete_forbidden' => 'No pots eliminar este document.',
        'update_forbidden' => 'No pots actualitzar este document.',
        'new_revision_forbidden' => 'No pots obrir una nova versió d\'este document.',
        'migrate_forbidden' => 'No pots migrar la plantilla d\'este document.',
        'review_required' => 'Es requerix permís per a revisar este document.',
        'block_update_required' => 'Es requerix permís per a actualitzar blocs d\'este document.',
        'index_required' => 'Es requerix permís document.index per a llistar documents.',
        'create_required' => 'Es requerix permís document.create per a crear documents.',
        'delegate_owner_only' => 'Només el titular pot delegar la titularitat del document.',
    ],

    'template_block' => [
        'create_required' => 'Es requerix permís per a crear blocs en esta plantilla.',
        'update_required' => 'Es requerix permís per a actualitzar blocs d\'esta plantilla.',
        'reorder_required' => 'Es requerix permís per a reordenar blocs d\'esta plantilla.',
    ],

    'comment' => [
        'create_required' => 'Es requerix permís per a comentar en este recurs.',
    ],

    'template' => [
        'assign_reviewers_required' => 'Es requerix permís per a assignar revisors de plantilla.',
        'assign_doc_reviewers_forbidden' => 'No pots assignar validadors de document en esta plantilla.',
        'new_revision_forbidden' => 'No pots obrir una nova versió d\'esta plantilla.',
        'index_required' => 'Es requerix permís.',
        'list_required' => 'Es requerix permís per a llistar plantilles.',
    ],

    'process' => [
        'index_required' => 'Es requerix permís process.index.',
        'show_required' => 'Es requerix permís process.show.',
    ],

    'review' => [
        'not_assigned' => 'No eres el revisor assignat a esta etapa.',
    ],

    'share' => [
        'owner_only' => 'Només el titular pot gestionar col·laboradors.',
    ],

    'process_context' => [
        'mismatch' => 'El context de procés no coincidix amb el recurs.',
    ],
];
