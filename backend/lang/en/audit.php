<?php

declare(strict_types=1);

return [
    'at_stage' => ' (stage :stage)',
    'by_reviewer' => ' by :reviewer',
    'unnamed' => 'unnamed',

    'template' => [
        'state_changed' => [
            'rejected' => 'Template ":name" rejected:stage_info:by_info',
            'published' => 'Template ":name" published:stage_info:by_info',
            'default' => 'Template ":name" status changed from :old to :new',
        ],
        'review_approved' => 'Stage :stage of template ":name" approved:by_info',
        'submitted_for_review' => 'Template ":name" submitted for review with :count reviewer(s)',
    ],

    'document' => [
        'state_changed' => [
            'rejected' => 'Document ":title" rejected:stage_info:by_info',
            'published' => 'Document ":title" published:stage_info:by_info',
            'default' => 'Document ":title" status changed from :old to :new',
        ],
        'review_approved' => 'Stage :stage of document ":title" approved:by_info',
        'submitted_for_review' => 'Document ":title" submitted for review with :count reviewer(s)',
    ],

    'template_version_block_layer' => [
        'included' => 'Block ":block_title" included in template version',
        'removed' => 'Block ":block_title" marked as removed in template version',
        'updated' => 'Block ":block_title" updated in template version',
    ],
];
