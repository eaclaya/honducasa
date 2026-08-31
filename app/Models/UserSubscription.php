<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\UserSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $subscription_plan_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $canceled_at
 * @property-read User $user
 * @property-read SubscriptionPlan $plan
 */
#[Fillable([
    'user_id',
    'subscription_plan_id',
    'status',
    'provider_customer_id',
    'provider_subscription_id',
    'current_period_ends_at',
    'grace_period_ends_at',
    'canceled_at',
])]

class UserSubscription extends Model
{
    /** @use HasFactory<UserSubscriptionFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_ends_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
