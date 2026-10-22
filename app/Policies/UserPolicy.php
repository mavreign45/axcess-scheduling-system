<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $target): bool
    {
        if ((int) $user->id === (int) $target->id) {
            return true;
        }

        return $user->isManager() && (int) $target->department_id === (int) $user->department_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->isManager() || $target->isAdmin() || $target->isManager()) {
            return false;
        }

        return (int) $target->department_id === (int) $user->department_id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isManager()
            && ! $target->isAdmin()
            && (int) $target->department_id === (int) $user->department_id
            && (int) $target->id !== (int) $user->id;
    }
}
