<?php

declare(strict_types=1);

namespace App\Http\Requests\Comments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function commentBody(): string
    {
        return (string) $this->string('body')->trim();
    }
}
