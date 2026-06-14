<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use App\Repositories\Resolvers\FdwUserProfileResolver;
use App\Services\UserProfileService;

/**
 * Perfil unificado del usuario activo resuelto por
 * {@see UserProfileService} (FDW o fallback JWT).
 *
 * Es un DTO local de salida del Service (distinto del DTO cross-app
 * {@see \Maya\Profile\Dtos\UserProfileDto} del paquete compartido, que se
 * construye aguas abajo en {@see FdwUserProfileResolver}).
 *
 * {@see toArray} produce la misma forma de array que el Service devolvía antes,
 * de modo que el caché y los consumidores existentes no cambian de contrato.
 */
final readonly class UserProfileDto
{
    /**
     * @param  list<string>  $studyTypeIds
     * @param  list<string>  $studyIds
     * @param  list<string>  $moduleIds
     * @param  list<string>  $teamIds
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $department,
        public string $locale,
        public array $studyTypeIds,
        public array $studyIds,
        public array $moduleIds,
        public array $teamIds,
        public array $permissions,
        public string $source,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            email: isset($row['email']) ? (string) $row['email'] : null,
            name: isset($row['name']) ? (string) $row['name'] : null,
            department: isset($row['department']) ? (string) $row['department'] : null,
            locale: (string) ($row['locale'] ?? 'es'),
            studyTypeIds: array_values((array) ($row['study_type_ids'] ?? [])),
            studyIds: array_values((array) ($row['study_ids'] ?? [])),
            moduleIds: array_values((array) ($row['module_ids'] ?? [])),
            teamIds: array_values((array) ($row['team_ids'] ?? [])),
            permissions: array_values((array) ($row['permissions'] ?? [])),
            source: (string) ($row['source'] ?? 'fdw'),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     email: ?string,
     *     name: ?string,
     *     department: ?string,
     *     locale: string,
     *     study_type_ids: list<string>,
     *     study_ids: list<string>,
     *     module_ids: list<string>,
     *     team_ids: list<string>,
     *     permissions: list<string>,
     *     source: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'department' => $this->department,
            'locale' => $this->locale,
            'study_type_ids' => $this->studyTypeIds,
            'study_ids' => $this->studyIds,
            'module_ids' => $this->moduleIds,
            'team_ids' => $this->teamIds,
            'permissions' => $this->permissions,
            'source' => $this->source,
        ];
    }
}
