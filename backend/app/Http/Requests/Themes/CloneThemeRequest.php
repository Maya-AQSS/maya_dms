<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\DTOs\Themes\CloneThemeDto;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

class CloneThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $theme = app(ThemeRepositoryInterface::class)->findModelOrFail($this->route('theme'));

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
            accessibilityOverrides: $v['accessibility'] ?? null,
        );
    }
}
