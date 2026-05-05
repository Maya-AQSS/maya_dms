<?php

/**
 * Favoritos mock (local / testing).
 *
 * - `user_id` debe existir en {@see database/data/users_mock.php}
 * - `template_id` en {@see database/data/templates_mock.php}
 * - `document_id` en {@see database/data/documents_mock.php}
 */
return [
    'favorite_templates' => [
        [
            'user_id'     => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
            'template_id' => '33333333-3333-3333-3333-333333333301',
        ],
        [
            'user_id'     => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
            'template_id' => '33333333-3333-3333-3333-333333333309',
        ],
    ],
    'favorite_documents' => [
        [
            'user_id'     => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
            'document_id' => '77777777-7777-7777-7777-777777777901',
        ],
    ],
];
