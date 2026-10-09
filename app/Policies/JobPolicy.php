<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            && $user->can('jobs.moderate');
    }

    public function view(User $user, Job $job): bool
    {
        if ($user->hasRole('super_admin')) {
            return $user->can('jobs.moderate');
        }

        return $user->partner_id === $job->partner_id
            && $user->can('jobs.view-own');
    }

    public function moderate(User $user, Job $job): bool
    {
        return $user->hasRole('super_admin')
            && $user->can('jobs.moderate');
    }
}