<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\DTOs\Themes\CreateThemeDto;
use App\Http\Requests\Themes\Concerns\SanitizesThemeLayout;
use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class StoreThemeRequest extends FormRequest
{
    use SanitizesThemeLayout;
    public function authorize(): bool
    {
        return $this->user()->can('create', Theme::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'team_id' => ['nullable', 'uuid'],

            'palette' => ['nullable', 'array'],
            'palette.primary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.secondary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.text' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.background' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'palette.accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],

            'typography' => ['nullable', 'array'],
            'typography.heading_font' => ['nullable', 'string', 'max:255'],
            'typography.body_font' => ['nullable', 'string', 'max:255'],
            'typography.base_size_pt' => ['nullable', 'integer', 'min:6', 'max:24'],
            'typography.line_height' => ['nullable', 'numeric', 'min:1.0', 'max:3.0'],

            'layout' => ['nullable', 'array'],
            'layout.regions' => ['nullable', 'array'],
            'layout.page' => ['nullable', 'array'],
            'layout.regions.*.props.src' => ['nullable', 'string', 'regex:/^themes\/[a-f0-9\-]{36}\/[a-f0-9\-]{36}$/'],
            'layout.regions.*.props.alt' => ['nullable', 'string', 'max:500'],
            'layout.regions.*.props.opacity' => ['nullable', 'numeric', 'between:0,1'],
            'layout.regions.*.props.rotate' => ['nullable', 'numeric', 'between:-360,360'],
            'layout.regions.*.props.objectFit' => ['nullable', 'string', 'in:cover,contain,stretch'],

            'accessibility' => ['nullable', 'array'],
            'accessibility.language' => ['nullable', 'string', 'size:2'],
            'accessibility.title' => ['nullable', 'string', 'max:255'],
            'accessibility.subject' => ['nullable', 'string', 'max:500'],
            'accessibility.author' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toCreateDto(): CreateThemeDto
    {
        $v = $this->validated();

        $defaults = [
            'palette' => [
                'primary' => '#0b5394',
                'secondary' => '#666666',
                'text' => '#1a1a1a',
                'background' => '#ffffff',
                'accent' => '#f59e0b',
            ],
            'typography' => [
                'heading_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
                'body_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
                'base_size_pt' => 11,
                'line_height' => 1.5,
            ],
            'layout' => [
                'regions' => [],
                'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2]],
            ],
            'accessibility' => [
                'language' => 'es',
                'title' => null,
                'subject' => null,
                'author' => 'CEEDCV',
            ],
        ];

        // El layout se toma del input crudo (no de validated()): las reglas
        // `layout.regions.*.props.*` validan campos concretos pero validated()
        // descartaría las claves sin regla (type, id, grid) y rompería la
        // estructura de las regions. La validación de seguridad (regex de src,
        // etc.) ya se aplicó sobre el input.
        $layoutInput = $this->stripDerivedLayoutFields((array) ($this->input('layout') ?? []));

        return new CreateThemeDto(
            name: (string) $v['name'],
            description: $v['description'] ?? null,
            teamId: $v['team_id'] ?? null,
            palette: array_replace($defaults['palette'], (array) ($v['palette'] ?? [])),
            typography: array_replace($defaults['typography'], (array) ($v['typography'] ?? [])),
            layout: array_replace($defaults['layout'], $layoutInput),
            accessibility: array_replace($defaults['accessibility'], (array) ($v['accessibility'] ?? [])),
        );
    }
}
