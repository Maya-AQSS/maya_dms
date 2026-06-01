<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AnchoredCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Resource-level authorization happens in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment_id' => ['required', 'uuid', 'exists:comments,id'],
            'anchor_from' => ['required', 'integer', 'min:0'],
            'anchor_to' => ['required', 'integer', 'gt:anchor_from'],
            'anchor_text_snapshot' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
