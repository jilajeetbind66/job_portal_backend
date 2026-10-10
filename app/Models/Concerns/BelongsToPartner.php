<?php
namespace App\Models\Concerns;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToPartner
{
    protected static function bootBelongsToPartner(): void
    {
        static::addGlobalScope('partner', function (Builder $builder) {
            $user = auth()->user();

            // Super admin can access jobs across all partners.
            if ($user?->hasRole('super_admin')) {
                return;
            }

            // Other users remain restricted to their own partner.
            if ($user?->partner_id !== null) {
                $builder->where(
                    $builder->getModel()->getTable() . '.partner_id',
                    $user->partner_id
                );
            }
        });

        static::creating(function ($model) {
            $user = auth()->user();

            // Automatically assign the authenticated user's partner.
            if ($user?->partner_id !== null) {
                $model->partner_id = $user->partner_id;
            }
        });
    }
}
