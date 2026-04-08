<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Listar plantillas.
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: filtros, paginación, TemplateService

        return response()->json(['data' => []]);
    }

    /**
     * Crear plantilla.
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: TemplateService::create(...)

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Mostrar plantilla.
     */
    public function show(Template $template): JsonResponse
    {
        // TODO: TemplateResource

        return response()->json(['data' => $template]);
    }

    /**
     * Actualizar plantilla.
     * La publicación de una plantilla no puede hacerlo el creador; exige otro actor autorizado vía {@see TemplatePolicy::review}.
     */
    public function update(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'study_id'        => ['sometimes', 'nullable', 'string'],
            'organization_id' => ['sometimes', 'nullable', 'string'],
            'status'          => ['sometimes', 'string', 'in:draft,published,archived'],
            'review_stages'   => ['sometimes', 'integer', 'min:0'],
            'review_mode'     => ['sometimes', 'string', 'in:sequential,parallel'],
        ]);

        $targetStatus = $validated['status'] ?? $template->status;

        if ($targetStatus === 'published' && $template->status !== 'published') {
            $this->authorize('review', $template);
        }

        // TODO: TemplateService::update(...)

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar plantilla.
     */
    public function destroy(Template $template): JsonResponse
    {
        // TODO: borrado lógico / política propia

        return response()->json(['message' => 'Not implemented'], 501);
    }
}
