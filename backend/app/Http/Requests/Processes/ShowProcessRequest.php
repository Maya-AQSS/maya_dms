<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\Models\Process;
use App\Services\Contracts\ProcessServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ShowProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->resolveProcess());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso process.show.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function resolveProcess(): Process
    {
        $id = (string) $this->route('process');

        return app(ProcessServiceInterface::class)->findModelOrFail($id);
    }
}
