<?php

namespace App\Models\Concerns;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToPartner
{
    protected static function bootBelongsToPartner(): void
    {
        static::addGlobalScope('partner', function (Builder $builder) {
            $user = auth()->user();

            if ($user?->partner_id !== null) {
                $builder->where(
                    $builder->getModel()->getTable() . '.partner_id',
                    $user->partner_id
                );
            }
        });

        static::creating(function ($model) {
            $user = auth()->user();

            if ($user?->partner_id !== null) {
                $model->partner_id = $user->partner_id;
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}