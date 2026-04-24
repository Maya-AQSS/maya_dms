<?php

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['nullable', 'string', 'max:255'],
            'default_content' => ['nullable', 'array'],
            'description'     => ['nullable', 'array'],
            'block_state'     => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }

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

    public function messages(): array
    {
        return [
            'block_state.in' => 'El estado del bloque debe ser uno de: '.implode(', ', BlockState::values()).'. Valor recibido: :input.',
        ];
    }

    private function sanitizeRichContent(mixed $value, ?string $parentKey = null): mixed
    {
        if (is_string($value)) {
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
