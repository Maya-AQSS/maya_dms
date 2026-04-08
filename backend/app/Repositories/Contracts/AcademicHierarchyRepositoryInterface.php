<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AcademicHierarchyRepositoryInterface
{
    /**
     * Get the complete academic hierarchy tree
     */
    public function getTree(): Collection;
}
