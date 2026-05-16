<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Atributo transitorio en modelos Eloquent antes de serializar con JsonResource.
 * No es columna de BD; solo transporta datos ya resueltos en la capa de servicio.
 */
final class ApiEmbeddedTeamResponse
{
    public const ATTRIBUTE_KEY = 'api_embedded_team';
}
