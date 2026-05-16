<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:255'],
            'permission' => ['required', 'string', 'in:read,edit'],
        ];
    }
}
