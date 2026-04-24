<?php

/**
 * Revisiones de documento (seed) para programaciones en `in_review`.
 * Los document_id deben existir en documents_mock.php.
 */
return [
    // 703 — plantilla módulo DWES 311: validadores de documento Dirección (1) y Secretaría (2)
    [
        'id' => '99999999-9999-9999-9999-999999999905',
        'document_id' => '77777777-7777-7777-7777-777777777703',
        'reviewer_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'stage' => 1,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999906',
        'document_id' => '77777777-7777-7777-7777-777777777703',
        'reviewer_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'stage' => 2,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999907',
        'document_id' => '77777777-7777-7777-7777-777777777704',
        'reviewer_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'stage' => 1,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999908',
        'document_id' => '77777777-7777-7777-7777-777777777704',
        'reviewer_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'stage' => 2,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
    // 705 — plantilla global 318: Secretaría (1) y Auditoría (2)
    [
        'id' => '99999999-9999-9999-9999-999999999909',
        'document_id' => '77777777-7777-7777-7777-777777777705',
        'reviewer_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'stage' => 1,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999910',
        'document_id' => '77777777-7777-7777-7777-777777777705',
        'reviewer_id' => 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc',
        'stage' => 2,
        'status' => 'pending',
        'rejection_reason' => null,
        'reviewed_at' => null,
    ],
];
