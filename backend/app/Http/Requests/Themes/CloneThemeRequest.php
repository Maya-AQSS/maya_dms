<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\DTOs\Themes\CloneThemeDto;
use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class CloneThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $theme = Theme::query()->findOrFail($this->route('theme'));

        return $this->user()->can('clone', $theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'palette' => ['nullable', 'array'],
            'typography' => ['nullable', 'array'],
            'layout' => ['nullable', 'array'],
            'assets' => ['nullable', 'array'],
            'accessibility' => ['nullable', 'array'],
        ];
    }

    public function toCloneDto(): CloneThemeDto
    {
        $v = $this->validated();

        return new CloneThemeDto(
            name: $v['name'] ?? null,
            paletteOverrides: $v['palette'] ?? null,
            typographyOverrides: $v['typography'] ?? null,
            layoutOverrides: $v['layout'] ?? null,
            assetsOverrides: $v['assets'] ?? null,
            accessibilityOverrides: $v['accessibility'] ?? null,
        );
    }
}
