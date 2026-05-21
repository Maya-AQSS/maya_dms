<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\Process;

class ProcessPolicy
{
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('process.index');
    }

    public function view(JwtUser $user, Process $process): bool
    {
        return $user->hasPermission('process.show');
    }

    public function create(JwtUser $user): bool
    {
        return $user->hasPermission('process.create');
    }

    public function update(JwtUser $user, Process $process): bool
    {
        return $user->hasPermission('process.update');
    }

    public function delete(JwtUser $user, Process $process): bool
    {
        return $user->hasPermission('process.delete');
    }
}
