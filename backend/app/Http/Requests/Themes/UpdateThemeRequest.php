<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\DTOs\Themes\UpdateThemeDto;
use App\Http\Requests\Themes\Concerns\SanitizesThemeLayout;
use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    use SanitizesThemeLayout;

    public function authorize(): bool
    {
        $theme = Theme::query()->findOrFail($this->route('theme'));

        return $this->user()->can('update', $theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // El estado no se edita vía PATCH: las transiciones pasan por los
            // endpoints dedicados POST /themes/{id}/publish y /archive.
            'status' => ['prohibited'],

            'palette' => ['sometimes', 'array'],
            'palette.primary' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.secondary' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.text' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.background' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.accent' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],

            'typography' => ['sometimes', 'array'],
            'typography.heading_font' => ['sometimes', 'string', 'max:255'],
            'typography.body_font' => ['sometimes', 'string', 'max:255'],
            'typography.base_size_pt' => ['sometimes', 'integer', 'min:6', 'max:24'],
            'typography.line_height' => ['sometimes', 'numeric', 'min:1.0', 'max:3.0'],

            'layout' => ['sometimes', 'array'],
            'layout.regions' => ['sometimes', 'array'],
            'layout.page' => ['sometimes', 'array'],
            'layout.regions.*.props.src' => ['nullable', 'string', 'regex:/^themes\/[a-f0-9\-]{36}\/[a-f0-9\-]{36}$/'],
            'layout.regions.*.props.alt' => ['nullable', 'string', 'max:500'],
            'layout.regions.*.props.opacity' => ['nullable', 'numeric', 'between:0,1'],
            'layout.regions.*.props.rotate' => ['nullable', 'numeric', 'between:-360,360'],
            'layout.regions.*.props.objectFit' => ['nullable', 'string', 'in:cover,contain,stretch'],

            // Geometría absoluta en mm (relativa a la esquina superior-izq).
            'layout.regions.*.box' => ['nullable', 'array'],
            'layout.regions.*.box.x' => ['nullable', 'numeric', 'between:0,2000'],
            'layout.regions.*.box.y' => ['nullable', 'numeric', 'between:0,2000'],
            'layout.regions.*.box.w' => ['nullable', 'numeric', 'between:0,2000'],
            'layout.regions.*.box.h' => ['nullable', 'numeric', 'between:0,2000'],
            'layout.regions.*.box.z' => ['nullable', 'integer', 'between:0,9999'],

            'accessibility' => ['sometimes', 'array'],
            'accessibility.language' => ['sometimes', 'string', 'size:2'],
            'accessibility.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accessibility.subject' => ['sometimes', 'nullable', 'string', 'max:500'],
            'accessibility.author' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function toUpdateDto(): UpdateThemeDto
    {
        $v = $this->validated();

        // Layout desde el input crudo (no validated()): preserva type/id/grid
        // de cada region; la validación de seguridad ya corrió sobre el input.
        $layout = $this->has('layout') ? $this->stripDerivedLayoutFields((array) $this->input('layout')) : null;

        return new UpdateThemeDto(
            name: $v['name'] ?? null,
            description: array_key_exists('description', $v) ? $v['description'] : null,
            status: null, // las transiciones de estado no pasan por PATCH (ver rules()).
            palette: $v['palette'] ?? null,
            typography: $v['typography'] ?? null,
            layout: $layout,
            accessibility: $v['accessibility'] ?? null,
        );
    }
}
