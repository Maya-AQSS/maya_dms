<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Editor backend
    |--------------------------------------------------------------------------
    |
    | Selects the renderer used to convert stored content into HTML at
    | request time:
    |   - "tiptap"   → Maya\Editor\Renderers\TiptapHtmlRenderer (default after Fase 6)
    |   - "blocknote" → App\Support\BlockNoteHtmlRenderer (legacy, used as
    |                   the rollback path while migrating)
    |
    | Switching back to `blocknote` requires the `content_legacy_blocknote`
    | backup column to still be present (it is dropped by
    | `php artisan blocknote:migrate-to-tiptap` after a clean run). If the
    | column was dropped, deploying with `blocknote` will not find the
    | original payload and the renderer will return empty HTML.
    |
    */
    'backend' => env('EDITOR_BACKEND', 'tiptap'),
];
