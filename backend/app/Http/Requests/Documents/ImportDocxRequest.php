<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class ImportDocxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller for this endpoint.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'], // KB → 50 MB
        ];
    }
}
