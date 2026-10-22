<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        if ((int) $leave->user_id === (int) $user->id) {
            return true;
        }

        return $user->isManager() && (int) $leave->user?->department_id === (int) $user->department_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeaveRequest $leave): bool
    {
        if ((int) $leave->user_id === (int) $user->id && $leave->status === 'pending') {
            return true;
        }

        return $user->isManager()
            && $leave->status === 'pending'
            && (int) $leave->user?->department_id === (int) $user->department_id;
    }

    public function approve(User $user, LeaveRequest $leave): bool
    {
        return $user->isManager()
            && $leave->status === 'pending'
            && (int) $leave->user?->department_id === (int) $user->department_id;
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        if ((int) $leave->user_id === (int) $user->id && $leave->status === 'pending') {
            return true;
        }

        return $user->isManager() && (int) $leave->user?->department_id === (int) $user->department_id;
    }

    public function delete(User $user, LeaveRequest $leave): bool
    {
        return (int) $leave->user_id === (int) $user->id && $leave->status === 'pending';
    }
}
