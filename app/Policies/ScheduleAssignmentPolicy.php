<?php

namespace App\Policies;

use App\Models\ScheduleAssignment;
use App\Models\User;

class ScheduleAssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduleAssignment $assignment): bool
    {
        if ($user->isManager()) {
            return (int) $assignment->department_id === (int) $user->department_id;
        }

        return (int) $assignment->user_id === (int) $user->id
            || (int) $assignment->replacement_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, ScheduleAssignment $assignment): bool
    {
        return $user->isManager() && (int) $assignment->department_id === (int) $user->department_id;
    }

    public function delete(User $user, ScheduleAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
