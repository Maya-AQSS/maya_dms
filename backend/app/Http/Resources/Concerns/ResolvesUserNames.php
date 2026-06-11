<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;

/**
 * Trait para Resources que necesitan resolver nombres de usuario por ID.
 *
 * Delega en UserDirectoryRepositoryInterface (nunca accede a DB directamente)
 * y mantiene una caché estática por proceso para evitar consultas duplicadas
 * dentro de una misma request.
 *
 * Opción elegida (3.7.2): trait en Resources en lugar de resolución previa en
 * el controller. Razón: TemplateVersionController construye colecciones
 * paginadas de EntityVersion que luego JsonResource serializa; cambiar las
 * firmas de ResourceCollection + controller para pasar mapas pre-resueltos
 * implicaría una refactorización mayor. El trait mantiene el cambio mínimo
 * (sustituye sólo DB::table por la interfaz de repositorio) sin alterar la
 * superficie pública de los Resources ni sus callers.
 */
trait ResolvesUserNames
{
    /**
     * Resuelve el nombre del usuario dado su ID, con caché estática por proceso.
     */
    protected function resolveUserNameById(string $userId): ?string
    {
        static $cache = [];

        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }

        /** @var UserDirectoryRepositoryInterface $repo */
        $repo = app(UserDirectoryRepositoryInterface::class);
        $cache[$userId] = $repo->findNameById($userId);

        return $cache[$userId];
    }
}
