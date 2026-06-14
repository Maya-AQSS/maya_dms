<?php

declare(strict_types=1);

namespace App\DTOs\Teams;

/**
 * Equipo (o departamento) visible para un usuario.
 *
 * Read-model FDW cross-app: el equipo no tiene entidad/modelo Eloquent local
 * de escritura, por lo que el DTO se construye desde la fila estructurada que
 * devuelve el repositorio ({@see fromRow}).
 */
final readonly class VisibleTeamDto
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isDepartment,
    ) {}

    /**
     * Construye el DTO desde una fila del repositorio.
     *
     * @param  array{id: mixed, name: mixed, is_department: mixed}  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            name: (string) $row['name'],
            isDepartment: (bool) $row['is_department'],
        );
    }

    /**
     * Forma serializable estable para la respuesta API.
     *
     * @return array{id: string, name: string, is_department: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_department' => $this->isDepartment,
        ];
    }
}
