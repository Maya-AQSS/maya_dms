<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ThemeFontController extends Controller
{
    /**
     * GET /api/v1/themes/fonts
     * Devuelve el whitelist completo de tipografías que el backend tiene
     * realmente instaladas. El frontend usa esta lista en el editor de Themes
     * para garantizar que el preview de navegador coincide con el PDF generado.
     *
     * No requiere parámetros. Cualquier usuario autenticado puede consultarlo.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => config('theme_fonts'),
        ]);
    }
}
