<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ExportPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is checked in the controller via $this->authorize() gate
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
