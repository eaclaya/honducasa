<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\TeamSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per subscription lifecycle attempt for a team. No row exists while
 * a team is on its free trial — that lives on `teams.trial_ends_at` — a row
 * only appears once the team actually checks out with a provider. A partial
 * unique index (see the migration) keeps at most one live row per team.
 *
 * @property int $id
 * @property int $team_id
 * @property int $subscription_plan_id
 * @property SubscriptionStatus $status
 * @property string|null $provider_customer_id
 * @property string|null $provider_subscription_id
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read SubscriptionPlan $plan
 */
#[Fillable([
    'team_id',
    'subscription_plan_id',
    'status',
    'provider_customer_id',
    'provider_subscription_id',
    'current_period_ends_at',
    'grace_period_ends_at',
    'canceled_at',
])]
class TeamSubscription extends Model
{
    /** @use HasFactory<TeamSubscriptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isLive(): bool
    {
        return $this->status !== SubscriptionStatus::Canceled;
    }

    /**
     * @return array<string, string>
     */
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
