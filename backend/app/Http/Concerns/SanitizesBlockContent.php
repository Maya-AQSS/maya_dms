<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Enums\BlockState;

/**
 * Normaliza campos de contenido enriquecido (title, description, default_content)
 * antes de la validación. Usado por los Form Requests de bloques de plantilla.
 */
trait SanitizesBlockContent
{
    /**
     * Prepara la validación.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('title')) {
            $title = trim((string) $this->input('title'));
            $payload['title'] = $title === '' ? null : $title;
        }

        if ($this->exists('description')) {
            $normalized = $this->sanitizeRichContent($this->input('description'));
            $payload['description'] = (is_array($normalized) || is_string($normalized)) ? $normalized : null;
        }

        if ($this->exists('default_content')) {
            $normalized = $this->sanitizeRichContent($this->input('default_content'));
            $payload['default_content'] = is_array($normalized) ? $normalized : null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * Mensajes de error para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'block_state.in' => 'El estado del bloque debe ser uno de: '.implode(', ', BlockState::values()).'. Valor recibido: :input.',
        ];
    }

    /**
     * Normaliza el contenido enriquecido.
     */
    private function sanitizeRichContent(mixed $value, ?string $parentKey = null): mixed
    {
        if (is_string($value)) {
            // Los campos 'text' dentro de BlockNote preservan sus espacios iniciales/finales
            if ($parentKey === 'text') {
                return $value === '' ? null : $value;
            }

            $normalized = trim($value);

            return $normalized === '' ? null : $normalized;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $sanitized = [];
            foreach ($value as $item) {
                $next = $this->sanitizeRichContent($item);
                if ($next !== null) {
                    $sanitized[] = $next;
                }
            }

            return $sanitized;
        }

        $out = [];
        foreach ($value as $key => $nested) {
            $next = $this->sanitizeRichContent($nested, (string) $key);
            if ($next !== null) {
                $out[(string) $key] = $next;
            }
        }

        // Preserve nodes even if they don't have nested meaningful content (like empty paragraphs)
        return $out;
    }
}
