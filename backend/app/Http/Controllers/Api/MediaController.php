<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * POST /api/v1/media
     *
     * Recibe una imagen, la almacena en el disco público y retorna su URL.
     * Usada por BlockNote para incrustar imágenes pegadas en bloques de contenido.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $ext  = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'png');
        $path = 'images/' . Str::uuid() . '.' . strtolower($ext);

        Storage::disk('public')->put($path, $file->getContent());

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }
}
