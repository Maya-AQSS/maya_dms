<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTemplateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
            'block_state' => ['required', 'string', 'in:'.implode(',', BlockState::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => __('validation.block_ids.required'),
            'ids.*.uuid' => __('validation.block_ids.uuid'),
            'block_state.required' => __('validation.block_state.required'),
            'block_state.in' => __('validation.block_state.in', ['values' => implode(', ', BlockState::values())]),
        ];
    }
}
