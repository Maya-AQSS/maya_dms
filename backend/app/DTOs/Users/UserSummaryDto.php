<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use App\Repositories\Eloquent\UserDirectoryRepository;

/**
 * Resumen de un usuario del directorio (búsqueda / candidatos a revisor).
 *
 * Read-model FDW cross-app: se construye desde la fila estructurada que devuelve
 * {@see UserDirectoryRepository} ({@see fromRow}).
 */
final readonly class UserSummaryDto
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $email,
        public ?string $role,
    ) {}

    /**
     * @param  array{id: mixed, name?: mixed, email?: mixed, role?: mixed}  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            name: isset($row['name']) ? (string) $row['name'] : null,
            email: isset($row['email']) ? (string) $row['email'] : null,
            role: isset($row['role']) ? (string) $row['role'] : null,
        );
    }

    /**
     * @return array{id: string, name: ?string, email: ?string, role: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
