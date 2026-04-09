<?php

namespace App\Services\Contracts;

use App\Models\Group;

interface GroupServiceInterface
{
    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Group;
}
