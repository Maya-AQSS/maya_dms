<?php

namespace App\Services\Contracts;

interface AcademicHierarchyServiceInterface
{
    /**
     * Get the cached complete academic hierarchy tree
     */
    public function getCachedTree(): array;
}
