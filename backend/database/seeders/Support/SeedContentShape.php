<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Support\MarkdownBlockRepair;

/**
 * Normaliza el contenido de los fixtures de bloques al shape canónico Tiptap
 * (ProseMirror) `{"type":"doc","content":[...]}` y lo serializa a JSON listo
 * para insertar.
 *
 * Los packs de seed (`programaciones_didacticas_*`) ahora se autoran
 * directamente en formato Tiptap nativo. Cualquier markdown literal escrito en
 * los textos del pack (p.ej. `**NOMBRE**`, `## Programa`) se convierte a nodos
 * reales en el seed via {@see MarkdownBlockRepair}, evitando que vuelva a
 * sembrarse contenido roto.
 */
final class SeedContentShape
{
    /**
     * Devuelve el contenido como JSON de un documento Tiptap.
     *
     * Acepta: documento Tiptap ya formado, o un string JSON con uno.
     * Idempotente: si es Tiptap válido, lo serializa tal cual.
     *
     * @param  mixed  $content
     */
    public static function toTiptapJson($content): string
    {
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($content) || $content === []) {
            return json_encode(['type' => 'doc', 'content' => []], JSON_UNESCAPED_UNICODE);
        }

        // Ya es un documento Tiptap → reparar markdown literal y pasar.
        if (($content['type'] ?? null) === 'doc') {
            $nodes = is_array($content['content'] ?? null) ? $content['content'] : [];
            $content['content'] = MarkdownBlockRepair::repair($nodes, includeCodeBlocks: true)['content'];

            return json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        // Array de bloques sin type=doc: reparar y envolver.
        if (array_is_list($content)) {
            $repaired = MarkdownBlockRepair::repair($content, includeCodeBlocks: true)['content'];

            return json_encode(['type' => 'doc', 'content' => $repaired], JSON_UNESCAPED_UNICODE);
        }

        // Forma inesperada: envolver defensivamente.
        return json_encode(['type' => 'doc', 'content' => []], JSON_UNESCAPED_UNICODE);
    }
}
