<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any partners.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('partners.view');
    }

    /**
     * Determine whether the user can view the partner.
     */
    public function view(User $user, Partner $partner): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->belongsToPartner($user, $partner->id)
            && $user->can('partner.view');
    }

    /**
     * Determine whether the user can create partners.
     */
    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('partners.create');
    }

    public function createAdminUser(User $user, Partner $partner): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('users.create');
    }

    /**
     * Determine whether the user can update the partner.
     */
    public function update(User $user, Partner $partner): bool
    {
        if ($this->isSuperAdmin($user)) {
            return $user->can('partners.update');
        }

        return $this->belongsToPartner($user, $partner->id)
            && $user->hasRole('partner_admin')
            && $user->can('partner.update');
    }

    /**
     * Determine whether the user can suspend or activate the partner.
     */
    public function changeStatus(User $user, Partner $partner): bool
    {
        return $this->isSuperAdmin($user)
            && $user->can('partners.suspend');
    }
}