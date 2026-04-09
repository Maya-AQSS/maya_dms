<?php

namespace App\Repositories\Contracts;

use App\Models\Group;

interface GroupRepositoryInterface
{
    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Group;
}
