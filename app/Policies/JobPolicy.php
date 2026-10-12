<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('jobs.moderate');
    }

    public function view(User $user, Job $job): bool
    {
        if ($this->isSuperAdmin($user)) {
            return $user->can('jobs.moderate');
        }

        return $this->belongsToPartner($user, $job->partner_id)
            && $user->can('jobs.view-own');
    }

    public function moderate(User $user, Job $job): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('jobs.moderate');
    }
}