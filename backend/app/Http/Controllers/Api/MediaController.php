<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const DISK = 'media';

    /**
     * POST /api/v1/media  (requiere JWT — ruta dentro del grupo auth)
     *
     * Almacena la imagen en disco privado con nombre {uuid} (sin extensión)
     * y devuelve una URL firmada con HMAC usable en <img src="...">.
     */
    private const ALLOWED_CONTEXT_TYPES = ['block', 'template', 'document', 'theme'];

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $contextType = $request->validated('context_type');
        $contextId   = $request->validated('context_id');
        $uuid        = Str::uuid()->toString();
        $path        = $contextType && $contextId
                       ? "{$contextType}s/{$contextId}/{$uuid}"
                       : "orphan/{$uuid}";

        Storage::disk(self::DISK)->put($path, $request->file('image')->getContent());

        $token = $this->makeToken($path);
        $base  = route('api.v1.media.show', ['uuid' => $uuid]);
        $url   = $contextType && $contextId
               ? "{$base}?ct={$contextType}&ci={$contextId}&token={$token}"
               : "{$base}?token={$token}";

        return response()->json(['url' => $url], 201);
    }

    /**
     * GET /api/v1/media/{uuid}?ct={type}&ci={id}&token={hmac}  (sin JWT)
     *
     * Valida el token HMAC firmado con APP_KEY y sirve el binario.
     * ?ct= context_type (block|template|document), ?ci= context_id (UUID).
     * El MIME se detecta desde el contenido real del fichero, no del nombre.
     */
    public function show(Request $request, string $uuid): Response
    {
        $contextType = (string) $request->query('ct', '');
        $contextId   = (string) $request->query('ci', '');
        $token       = (string) $request->query('token', '');

        // Validate context params before constructing path to prevent path traversal.
        if ($contextType !== '') {
            if (! in_array($contextType, self::ALLOWED_CONTEXT_TYPES, true) || ! Str::isUuid($contextId)) {
                abort(403, 'Token de media inválido.');
            }
        }

        $path = $contextType && $contextId
              ? "{$contextType}s/{$contextId}/{$uuid}"
              : "orphan/{$uuid}";

        if (! hash_equals($this->makeToken($path), $token)) {
            abort(403, 'Token de media inválido.');
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            abort(404, 'Imagen no encontrada.');
        }

        $content  = $disk->get($path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';

        return response($content, 200, [
            'Content-Type'           => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=31536000, immutable',
        ]);
    }

    private function makeToken(string $path): string
    {
        return hash_hmac('sha256', $path, (string) config('app.key'));
    }
}
