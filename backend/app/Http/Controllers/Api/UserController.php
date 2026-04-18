<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JwtUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * GET /api/v1/users?search={term}&per_page={n}
     *
     * F-05.1 describe colaboradores/revisores pero no el endpoint `/users`; el acceso
     * con `users.search` es decisión de producto hasta que el backlog lo cierre.
     *
     * Búsqueda case-insensitive por nombre, email y departamento.
     * Devuelve { data: [...] } con el campo `role` mapeado desde `department`.
     *
     * Devuelve array vacío si el término tiene menos de 2 caracteres.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $term = '%' . mb_strtolower($search) . '%';

        // DB::table evita que Eloquent castee el id (string) a int.
        $users = DB::table('users')
            ->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(name) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(department) LIKE ?', [$term]);
            })
            ->select('id', 'name', 'email', 'department')
            ->limit($perPage)
            ->get()
            ->map(static fn (object $u): array => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->department, // department → role para el frontend
            ]);

        return response()->json(['data' => $users]);
    }
}
