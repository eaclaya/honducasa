<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Property> $properties
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Collection<int, TeamSubscription> $subscriptions
 */
#[Fillable(['name', 'slug', 'is_personal', 'suspended_at', 'suspension_reason', 'trial_ends_at'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all properties owned by this team.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return HasMany<TeamSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TeamSubscription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'suspended_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * A suspended team keeps its listings but none of them stay public.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * The live (non-canceled) subscription for this team, if any. At most
     * one exists at a time — see the partial unique index on
     * `team_subscriptions`.
     */
    public function activeSubscription(): ?TeamSubscription
    {
        return $this->subscriptions()->whereNot('status', SubscriptionStatus::Canceled)->first();
    }

    /**
     * A team is on trial while it has no live subscription and its trial
     * window hasn't closed. Subscribing early ends the trial even if
     * `trial_ends_at` is still in the future.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture()
            && $this->activeSubscription() === null;
    }

    /**
     * The plan governing this team's limits right now: their subscribed
     * plan, or the free entry tier while on trial, or null once the trial
     * has lapsed with nothing subscribed.
     */
    public function currentPlan(): ?SubscriptionPlan
    {
        if ($subscription = $this->activeSubscription()) {
            return $subscription->plan;
        }

        if ($this->isOnTrial()) {
            return SubscriptionPlan::query()
                ->entryTierFor(SubscriptionLadder::forTeam($this->is_personal))
                ->first();
        }

        return null;
    }

    /**
     * Whether this team has room under its current plan to publish one more
     * listing. A team with no plan at all — trial lapsed, never subscribed —
     * can't publish anything until it does.
     *
     * A team with a null `trial_ends_at` and no subscription predates the
     * subscription system and is grandfathered in as unrestricted, rather
     * than retroactively locked out.
     */
    public function canPublishAnotherListing(): bool
    {
        if ($this->trial_ends_at === null && $this->activeSubscription() === null) {
            return true;
        }

        $plan = $this->currentPlan();

        if ($plan === null) {
            return false;
        }

        return $plan->active_listings_limit === null
            || $this->properties()->where('status', ListingStatus::Published)->count() < $plan->active_listings_limit;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
