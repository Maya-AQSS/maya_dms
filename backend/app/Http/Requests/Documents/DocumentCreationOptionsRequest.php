<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\JwtUser;
use Illuminate\Foundation\Http\FormRequest;

class DocumentCreationOptionsRequest extends FormRequest
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
        ];
    }
}
