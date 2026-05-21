<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadThemeAssetRequest extends FormRequest
{
    /** Tipos de asset que un theme puede tener. Mapea 1:1 con keys de Theme.assets. */
    public const VALID_KINDS = ['logo', 'background', 'watermark'];

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
            'kind' => ['required', 'string', Rule::in(self::VALID_KINDS)],
            'file' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,svg,webp',
                'max:5120', // 5 MB
            ],
        ];
    }

    public function kind(): string
    {
        return (string) $this->validated()['kind'];
    }

    public function fileColumn(): string
    {
        // Asset key in Theme.assets JSONB. Equivalente a:
        //   logo → logo_path, background → background_image_path, watermark → watermark_path
        return match ($this->kind()) {
            'background' => 'background_image_path',
            default => $this->kind().'_path',
        };
    }
}
