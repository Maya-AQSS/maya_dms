<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Maya\Editor\Renderers\BlockNoteToTiptap;

/**
 * Normaliza el contenido de los fixtures de bloques al shape canónico Tiptap
 * (ProseMirror) `{"type":"doc","content":[...]}` y lo serializa a JSON listo
 * para insertar.
 *
 * Los packs de seed (`programaciones_didacticas_*`) se siguen autorando en el
 * formato legacy BlockNote (array de bloques con `type/props/content/children`)
 * porque es más legible, pero la BD ya solo almacena Tiptap: la conversión se
 * hace aquí, en el borde del seeder, con el mismo converter probado que usó la
 * migración de datos. Así el render (TiptapHtmlRenderer) y el editor reciben
 * siempre Tiptap, sin ramas legacy en runtime.
 */
final class SeedContentShape
{
    /**
     * Devuelve el contenido como JSON de un documento Tiptap.
     *
     * Acepta: array de bloques BlockNote, documento Tiptap ya formado, o un
     * string JSON con cualquiera de los dos. Idempotente: si ya es Tiptap, no
     * lo vuelve a convertir.
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

        // Ya es un documento Tiptap → pasar tal cual.
        if (($content['type'] ?? null) === 'doc') {
            return json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        // Array de bloques BlockNote (lista) → convertir.
        if (array_is_list($content)) {
            return json_encode(BlockNoteToTiptap::convert($content), JSON_UNESCAPED_UNICODE);
        }

        // Forma desconocida no-lista y sin type=doc: envolver defensivamente.
        return json_encode(['type' => 'doc', 'content' => []], JSON_UNESCAPED_UNICODE);
    }
}
