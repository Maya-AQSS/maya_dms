<?php

declare(strict_types=1);

return [
    'document' => [
        'delete_forbidden' => 'You cannot delete this document.',
        'update_forbidden' => 'You cannot update this document.',
        'new_revision_forbidden' => 'You cannot open a new version of this document.',
        'migrate_forbidden' => 'You cannot migrate the template of this document.',
        'review_required' => 'Permission is required to review this document.',
        'block_update_required' => 'Permission is required to update blocks of this document.',
        'index_required' => 'The document.index permission is required to list documents.',
        'create_required' => 'The document.create permission is required to create documents.',
        'delegate_owner_only' => 'Only the owner can delegate ownership of the document.',
    ],

    'template_block' => [
        'create_required' => 'Permission is required to create blocks in this template.',
        'update_required' => 'Permission is required to update blocks of this template.',
        'reorder_required' => 'Permission is required to reorder blocks of this template.',
    ],

    'comment' => [
        'create_required' => 'Permission is required to comment on this resource.',
    ],

    'template' => [
        'assign_reviewers_required' => 'Permission is required to assign template reviewers.',
        'assign_doc_reviewers_forbidden' => 'You cannot assign document reviewers on this template.',
        'new_revision_forbidden' => 'You cannot open a new version of this template.',
        'index_required' => 'Permission is required.',
        'list_required' => 'Permission is required to list templates.',
    ],

    'process' => [
        'index_required' => 'The process.index permission is required.',
        'show_required' => 'The process.show permission is required.',
    ],

    'review' => [
        'not_assigned' => 'You are not the reviewer assigned to this stage.',
    ],

    'share' => [
        'owner_only' => 'Only the owner can manage collaborators.',
    ],

    'process_context' => [
        'mismatch' => 'The process context does not match the resource.',
    ],
];
