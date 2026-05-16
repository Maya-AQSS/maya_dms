<?php
declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class StartNewTemplateRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = Template::query()->findOrFail($this->route('template'));

        return $this->user()->can('startRevision', $template);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
