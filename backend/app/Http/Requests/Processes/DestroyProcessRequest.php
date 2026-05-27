<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;

class DestroyProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->resolveProcess());
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

        return Process::findOrFail($id);
    }
}
