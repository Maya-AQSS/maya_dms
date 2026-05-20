<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\JwtUser;
use Illuminate\Foundation\Http\FormRequest;

class DocumentCreateFromModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof JwtUser && $user->hasPermission('document.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'module_id' => ['required', 'string'],
            'process_id' => ['required', 'uuid', 'exists:processes,id'],
            'template_version_id' => ['sometimes', 'nullable', 'uuid'],
            'delivery_deadline' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
