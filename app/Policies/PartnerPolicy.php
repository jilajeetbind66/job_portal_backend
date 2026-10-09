<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
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
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->partner_id === $partner->id
            && $user->can('partner.view');
    }

    /**
     * Determine whether the user can create partners.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin')
            && $user->can('partners.create');
    }

    /**
     * Determine whether the user can update the partner.
     */
    public function update(User $user, Partner $partner): bool
    {
        if ($user->hasRole('super_admin')) {
            return $user->can('partners.update');
        }

        return $user->partner_id === $partner->id
            && $user->hasRole('partner_admin')
            && $user->can('partner.update');
    }

    /**
     * Determine whether the user can suspend or activate the partner.
     */
    public function changeStatus(User $user, Partner $partner): bool
    {
        return $user->hasRole('super_admin')
            && $user->can('partners.suspend');
    }
}