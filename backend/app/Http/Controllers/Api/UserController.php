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
                'role'  => $u->department,
            ]);

        return response()->json(['data' => $users]);
    }

    /**
     * GET /api/v1/users/reviewer-candidates?search={term}&per_page={n}
     *
     * Devuelve los usuarios que tienen el permiso `templates.review` y, por tanto,
     * pueden ser seleccionados como revisores de una plantilla normativa.
     * El front no necesita conocer el código de permiso interno.
     *
     * `search` es opcional; si se omite devuelve todos los candidatos (hasta per_page).
     */
    public function reviewerCandidates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);

        $query = DB::table('users')
            ->join('user_permissions', 'users.id', '=', 'user_permissions.user_id')
            ->where('user_permissions.permission_code', 'templates.review');

        if (mb_strlen($search) >= 2) {
            $term = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(users.name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(users.email) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(users.department) LIKE ?', [$term]);
            });
        }

        $users = $query
            ->select('users.id', 'users.name', 'users.email', 'users.department')
            ->limit($perPage)
            ->get()
            ->map(static fn (object $u): array => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->department,
            ]);

        return response()->json(['data' => $users]);
    }
}
