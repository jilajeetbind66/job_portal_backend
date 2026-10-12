<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    protected function belongsToPartner(User $user, ?int $partnerId): bool
    {
        return $partnerId !== null
            && $user->partner_id !== null
            && (int) $user->partner_id === $partnerId;
    }
}