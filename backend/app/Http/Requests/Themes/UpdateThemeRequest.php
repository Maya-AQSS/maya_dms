<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\DTOs\Themes\UpdateThemeDto;
use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
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
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],

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

            'assets' => ['sometimes', 'array'],
            'assets.logo_path' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'assets.background_image_path' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'assets.watermark_path' => ['sometimes', 'nullable', 'string', 'max:1024'],

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

        return new UpdateThemeDto(
            name: $v['name'] ?? null,
            description: array_key_exists('description', $v) ? $v['description'] : null,
            status: $v['status'] ?? null,
            palette: $v['palette'] ?? null,
            typography: $v['typography'] ?? null,
            layout: $v['layout'] ?? null,
            assets: $v['assets'] ?? null,
            accessibility: $v['accessibility'] ?? null,
        );
    }
}
