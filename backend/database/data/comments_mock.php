<?php

/**
 * Comentarios mock.
 *
 * - document_id / document_block_id deben existir tras DocumentsSeeder y DocumentBlocksSeeder.
 * - author_id en users_mock.php.
 */
return [
    [
        'id' => '99999999-9999-9999-9999-999999999901',
        'document_id' => '77777777-7777-7777-7777-777777777701',
        'document_block_id' => null,
        'parent_id' => null,
        'author_id' => '2ead4bf3-574c-41b4-95ca-cac7daed0664',
        'body' => 'Comentario general: revisar el título antes de enviar a revisión.',
        'type' => 'general',
        'resolved' => false,
        'resolved_by' => null,
        'resolved_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999902',
        'document_id' => '77777777-7777-7777-7777-777777777701',
        'document_block_id' => '88888888-8888-8888-8888-888888888801',
        'parent_id' => null,
        'author_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'body' => 'Comentario en el bloque título: usar mayúsculas en el encabezado.',
        'type' => 'general',
        'resolved' => false,
        'resolved_by' => null,
        'resolved_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999903',
        'document_id' => '77777777-7777-7777-7777-777777777701',
        'document_block_id' => null,
        'parent_id' => '99999999-9999-9999-9999-999999999901',
        'author_id' => 'ed568442-ece5-4c90-97ca-12c8969bb3a2',
        'body' => 'De acuerdo, lo aplicamos en la siguiente edición.',
        'type' => 'general',
        'resolved' => false,
        'resolved_by' => null,
        'resolved_at' => null,
    ],
    [
        'id' => '99999999-9999-9999-9999-999999999904',
        'document_id' => '77777777-7777-7777-7777-777777777702',
        'document_block_id' => '88888888-8888-8888-8888-888888888802',
        'parent_id' => null,
        'author_id' => '50f503c6-cb63-466c-852d-0b30ae130e98',
        'body' => 'Nota en plantilla por grupo: completar descripción.',
        'type' => 'review',
        'resolved' => false,
        'resolved_by' => null,
        'resolved_at' => null,
    ],
];
