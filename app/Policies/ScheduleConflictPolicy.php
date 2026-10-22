<?php

namespace App\Policies;

use App\Models\ScheduleConflict;
use App\Models\User;

class ScheduleConflictPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, ScheduleConflict $conflict): bool
    {
        return $user->isManager() && (int) $conflict->department_id === (int) $user->department_id;
    }

    public function resolve(User $user, ScheduleConflict $conflict): bool
    {
        return $this->view($user, $conflict);
    }
}
