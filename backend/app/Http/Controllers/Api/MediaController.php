<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    /**
     * POST /api/v1/media  (requiere JWT — ruta dentro del grupo auth)
     *
     * Almacena la imagen en disco privado con nombre {uuid} (sin extensión)
     * y devuelve una URL firmada con HMAC usable en <img src="...">.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $contextType = $request->validated('context_type');
        $contextId = $request->validated('context_id');
        $file = $request->file('image');

        $dto = $this->mediaService->store($file, $contextType, $contextId);

        return response()->json(new MediaResource($dto), 201);
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
        $contextType = (string) $request->query('ct', '') ?: null;
        $contextId = (string) $request->query('ci', '') ?: null;
        $token = (string) $request->query('token', '');

        // El Service lanza excepciones HTTP tipadas (AccessDeniedHttpException 403 /
        // NotFoundHttpException 404) que el handler de Laravel traduce al status
        // correcto, sin acoplar el controlador al texto de los mensajes.
        $content = $this->mediaService->retrieve($uuid, $contextType, $contextId, $token);
        $mimeType = $this->mediaService->detectMimeType($content);

        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ];

        // Defensa en profundidad anti-XSS: aunque ThemeImageService rechaza SVG con
        // <script>, event handlers o javascript:, cualquier SVG (incl. los antiguos
        // ya almacenados) se sirve como descarga con CSP estricta y MIME genérico
        // para que el navegador no lo ejecute si llega por una URL directa.
        if ($mimeType === 'image/svg+xml' || $mimeType === 'image/svg') {
            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Disposition'] = 'attachment; filename="'.$uuid.'.svg"';
            $headers['Content-Security-Policy'] = "default-src 'none'; sandbox";
        }

        return response($content, 200, $headers);
    }
}
