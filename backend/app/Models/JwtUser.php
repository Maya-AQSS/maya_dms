<?php
declare(strict_types=1);

namespace App\Models;

use Maya\Auth\Models\BaseJwtUser;

/**
 * DTO específico de DMS: extiende {@see \Maya\Auth\Models\BaseJwtUser} añadiendo
 * los IDs de scope académico (study types, studies, modules, teams).
 *
 * El guard `api` fusiona el JWT con {@see \App\Services\UserProfileService::getProfile()}:
 * ámbito académico y equipos vienen de la BD (FDW / mocks); si cae en fallback JWT,
 * esas listas equivalen a los claims del token.
 *
 * Los roles de realm (Keycloak) no se materializan aquí: la autorización de
 * negocio usa {@see hasPermission()} con los códigos resueltos en el perfil.
 */
class JwtUser extends BaseJwtUser
{
    /**
     * IDs de contexto académico.
     *
     * @var list<string>
     */
    public readonly array $studyTypeIds;

    /**
     * IDs de estudios.
     *
     * @var list<string>
     */
    public readonly array $studyIds;

    /**
     * IDs de módulos.
     *
     * @var list<string>
     */
    public readonly array $moduleIds;

    /**
     * IDs de equipos.
     *
     * @var list<string>
     */
    public readonly array $teamIds;

    public function __construct(array $claims)
    {
        parent::__construct($claims);

        $this->studyTypeIds = self::mergeScopeIds(
            $claims['study_type_ids'] ?? null,
            $claims['study_type_id'] ?? null,
        );
        $this->studyIds = self::mergeScopeIds(
            $claims['study_ids'] ?? null,
            $claims['study_id'] ?? null,
        );
        $this->moduleIds = array_values(array_unique(array_merge(
            self::mergeScopeIds($claims['module_ids'] ?? null, $claims['module_id'] ?? null),
            self::mergeScopeIds($claims['course_module_ids'] ?? null, $claims['course_module_id'] ?? null),
        )));
        $this->teamIds = self::mergeScopeIds(
            $claims['team_ids'] ?? null,
            $claims['team_id'] ?? null,
        );
    }
}
