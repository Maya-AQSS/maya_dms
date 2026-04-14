<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría inmutable (ISO 9001 / RGPD).
 *
 * Restricciones de integridad:
 *   - $timestamps = false: Laravel no gestiona created_at/updated_at.
 *     El campo 'timestamp' lo establece PostgreSQL con DEFAULT NOW().
 *   - 'timestamp' está excluido de $fillable: el código de aplicación
 *     nunca puede enviarlo; siempre es el reloj del servidor.
 *   - No se exponen métodos de actualización ni borrado.
 *     A nivel de BD el usuario de app solo tiene INSERT + SELECT.
 */
class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'audit_log';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'block_uuid',
        'action',
        'user_id',
        'ip_address',
        'user_agent',
        'previous_value',
        'new_value',
    ];

    protected function casts(): array
    {
        return [
            'previous_value' => 'array',
            'new_value'      => 'array',
        ];
    }

    /**
     * Mapa de valores permitidos en entity_type → clase del modelo correspondiente.
     * Usado por Observers, Policy y validaciones para lookups dinámicos.
     */
    public static function allowedEntityTypes(): array
    {
        return [
            'document'       => Document::class,
            'template'       => Template::class,
            'comment'        => Comment::class,
            'template_block' => TemplateBlock::class,
        ];
    }
}
