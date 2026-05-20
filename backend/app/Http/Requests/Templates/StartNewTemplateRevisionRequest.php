<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class StartNewTemplateRevisionRequest extends FormRequest
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
        return [];
    }
}
