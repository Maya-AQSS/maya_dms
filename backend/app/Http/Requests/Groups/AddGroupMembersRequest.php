<?php

namespace App\Http\Requests\Groups;

use App\Services\Contracts\GroupServiceInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

class AddGroupMembersRequest extends FormRequest
{
    /**
     * Verifica si el usuario tiene permisos para agregar miembros a un grupo.
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

        return $this->user()->can('manageMembers', $group);
    }

    /**
     * Define las reglas de validación para la solicitud de agregación de miembros.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required_without:user_ids', 'nullable', 'string', 'max:255'],
            'user_ids' => ['required_without:user_id', 'nullable', 'array', 'max:500'],
            'user_ids.*' => ['string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:member,admin'],
        ];
    }

    /**
     * Valida la solicitud de agregación de miembros.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $uid = $this->input('user_id');
            $uids = $this->input('user_ids', []);
            $list = is_array($uids) ? array_filter($uids, fn ($x) => $x !== null && $x !== '') : [];
            if (($uid === null || $uid === '') && $list === []) {
                $v->errors()->add('user_id', __('validation.required'));
            }
        });
    }
}
