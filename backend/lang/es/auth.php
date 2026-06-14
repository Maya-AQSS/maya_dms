<?php

return [
    'document' => [
        'delete_forbidden' => 'No puedes eliminar este documento.',
        'update_forbidden' => 'No puedes actualizar este documento.',
        'new_revision_forbidden' => 'No puedes abrir una nueva versión de este documento.',
        'migrate_forbidden' => 'No puedes migrar la plantilla de este documento.',
        'review_required' => 'Se requiere permiso para revisar este documento.',
        'block_update_required' => 'Se requiere permiso para actualizar bloques de este documento.',
        'index_required' => 'Se requiere permiso document.index para listar documentos.',
        'create_required' => 'Se requiere permiso document.create para crear documentos.',
        'delegate_owner_only' => 'Solo el titular puede delegar la titularidad del documento.',
    ],

    'template_block' => [
        'create_required' => 'Se requiere permiso para crear bloques en esta plantilla.',
        'update_required' => 'Se requiere permiso para actualizar bloques de esta plantilla.',
        'reorder_required' => 'Se requiere permiso para reordenar bloques de esta plantilla.',
    ],

    'comment' => [
        'create_required' => 'Se requiere permiso para comentar en este recurso.',
    ],

    'template' => [
        'assign_reviewers_required' => 'Se requiere permiso para asignar revisores de plantilla.',
        'assign_doc_reviewers_forbidden' => 'No puedes asignar validadores de documento en esta plantilla.',
        'new_revision_forbidden' => 'No puedes abrir una nueva versión de esta plantilla.',
        'index_required' => 'Se requiere permiso.',
        'list_required' => 'Se requiere permiso para listar plantillas.',
    ],

    'process' => [
        'index_required' => 'Se requiere permiso process.index.',
        'show_required' => 'Se requiere permiso process.show.',
    ],

    'review' => [
        'not_assigned' => 'No eres el revisor asignado a esta etapa.',
    ],

    'share' => [
        'owner_only' => 'Solo el titular puede gestionar colaboradores.',
    ],

    'process_context' => [
        'mismatch' => 'El contexto de proceso no coincide con el recurso.',
    ],
];
