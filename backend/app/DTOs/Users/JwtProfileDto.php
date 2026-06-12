<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use Maya\Auth\Dtos\JwtProfileDto as BaseJwtProfileDto;

/**
 * DTO que encapsula los datos mínimos extraídos del JWT para fallback de perfil.
 *
 * Extiende el DTO base compartido ({@see BaseJwtProfileDto}, readonly no-final
 * con `id` + `claims`) añadiendo los claims académicos tipados propios de dms.
 * Los claims crudos completos quedan accesibles vía `$claims` (propiedad del base).
 *
 * Se usa en UserProfileService::getProfile() como parámetro tipado en lugar de
 * un array sin estructura. Contiene solo los claims accesibles del token que pueden
 * servir como fallback si la consulta FDW falla.
 */
final readonly class JwtProfileDto extends BaseJwtProfileDto
{
    /**
     * @param  array<string, mixed>  $claims  Claims crudos completos del JWT.
     */
    public function __construct(
        string $id,
        array $claims = [],
        public ?string $email = null,
        public ?string $name = null,
        public ?string $department = null,
        public ?string $departamento = null,
        /** @var list<string> */
        public array $studyTypeIds = [],
        /** @var list<string> */
        public array $studyIds = [],
        /** @var list<string> */
        public array $moduleIds = [],
        /** @var list<string> */
        public array $courseModuleIds = [],
    ) {
        parent::__construct(id: $id, claims: $claims);
    }

    /**
     * Construye el DTO desde un array raw del JWT (típicamente de $jwtProfile del middleware).
     *
     * A diferencia del base (que devuelve null si falta `id`), conserva el
     * contrato histórico de dms: siempre devuelve instancia (id '' si falta).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            claims: $data,
            email: self::nullableString($data['email'] ?? null),
            name: self::nullableString($data['name'] ?? null),
            department: self::nullableString($data['department'] ?? null),
            departamento: self::nullableString($data['departamento'] ?? null),
            studyTypeIds: self::arrayList($data['study_type_ids'] ?? $data['study_type_id'] ?? []),
            studyIds: self::arrayList($data['study_ids'] ?? $data['study_id'] ?? []),
            moduleIds: self::arrayList($data['module_ids'] ?? $data['module_id'] ?? []),
            courseModuleIds: self::arrayList($data['course_module_ids'] ?? $data['course_module_id'] ?? []),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function arrayList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_map(static fn ($v): string => (string) $v, array_filter($value))
        );
    }
}
