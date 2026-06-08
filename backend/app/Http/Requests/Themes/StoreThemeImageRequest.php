<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class StoreThemeImageRequest extends FormRequest
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
            'file' => ['required_without:url', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:10240'],
            'url' => ['required_without:file', 'string', 'url', 'max:2048'],
        ];
    }
}
