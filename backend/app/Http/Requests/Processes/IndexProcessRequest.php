<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\Models\Process;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Process::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso process.index.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
