<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Template;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $contextType = $this->input('context_type');
        $contextId   = $this->input('context_id');

        if (! $contextType || ! $contextId) {
            return true;
        }

        return match ($contextType) {
            'template' => $this->user()->can(
                'update',
                app(TemplateServiceInterface::class)->findModelOrFail($contextId),
            ),
            'document' => $this->user()->can(
                'update',
                app(DocumentServiceInterface::class)->findModelOrFail($contextId),
            ),
            'theme' => $this->user()->can(
                'update',
                app(ThemeRepositoryInterface::class)->findModelOrFail($contextId),
            ),
            'block' => $this->user()->can(
                'update',
                $this->resolveTemplateForBlock($contextId),
            ),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'context_type' => ['nullable', 'string', 'in:block,template,document,theme'],
            'context_id' => ['nullable', 'uuid', 'required_with:context_type'],
        ];
    }

    /**
     * Para context_type=block la autorización se evalúa sobre la plantilla padre,
     * igual que en TemplateBlockController: repository de bloque → template_id → servicio de plantilla.
     */
    private function resolveTemplateForBlock(string $blockId): Template
    {
        $block = app(TemplateBlockRepositoryInterface::class)->findOrFail($blockId);

        return app(TemplateServiceInterface::class)->findModelOrFail($block->template_id);
    }
}
