<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\Repositories\Contracts\ThemeRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

class StoreThemeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $theme = app(ThemeRepositoryInterface::class)->findModelOrFail($this->route('theme'));

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
