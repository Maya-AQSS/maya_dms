<?php

namespace App\Http\Requests\Groups;

use App\Services\Contracts\GroupServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Verifica si el usuario tiene permisos para actualizar un grupo.
     */
    public function authorize(): bool
    {
        $id = $this->route('group');
        if (! is_string($id)) {
            return false;
        }

        try {
            $group = app(GroupServiceInterface::class)->findOrFail($id);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return $this->user()->can('update', $group);
    }

    /**
     * Define las reglas de validación para la solicitud de actualización de un grupo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
