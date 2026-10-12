<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return $user->can('users.view');
        }

        return $user->hasRole('partner_admin')
            && $user->partner_id !== null
            && $user->can('partner.users.manage');
    }

    public function view(User $user, User $targetUser): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->isSuperAdmin($user)) {
            return $user->can('users.view');
        }

        /*
        |--------------------------------------------------------------------------
        | Partner Admin
        |--------------------------------------------------------------------------
        */

        return $this->belongsToPartner($user, $targetUser->partner_id)
            && $user->hasRole('partner_admin')
            && $user->can('partner.users.manage');
    }

    public function create(User $user): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->isSuperAdmin($user)) {
            return $user->can('users.create');
        }

        /*
        |--------------------------------------------------------------------------
        | Partner Admin
        |--------------------------------------------------------------------------
        */

        return $user->partner_id !== null
            && $user->hasRole('partner_admin')
            && $user->can('partner.users.manage');
    }

    public function update(User $user, User $targetUser): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->isSuperAdmin($user)) {
            return $user->can('users.update');
        }

        /*
        |--------------------------------------------------------------------------
        | Partner Admin
        |--------------------------------------------------------------------------
        */

        return $this->belongsToPartner($user, $targetUser->partner_id)
            && $user->hasRole('partner_admin')
            && $user->can('partner.users.manage');
    }

    public function changeStatus(User $user, User $targetUser): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->isSuperAdmin($user)) {
            return $user->can('users.suspend');
        }

        /*
        |--------------------------------------------------------------------------
        | Partner Admin
        |--------------------------------------------------------------------------
        */

        return $this->belongsToPartner($user, $targetUser->partner_id)
            && $user->hasRole('partner_admin')
            && $user->can('partner.users.manage');
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('users.delete')
            && $user->id !== $targetUser->id;
    }
}