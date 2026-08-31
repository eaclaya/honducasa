<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $individual_trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Property> $createdProperties
 * @property-read Collection<int, OauthIdentity> $oauthIdentities
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Collection<int, Message> $sentMessages
 * @property-read Collection<int, ModerationStrike> $moderationStrikes
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'individual_trial_ends_at', 'is_admin', 'suspended_at', 'suspension_reason'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, HasMedia, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The locale for mail and notifications. There's no per-user preference
     * stored yet — the UI's locale switcher only persists to the session,
     * which a queued notification never has access to — so every user gets
     * the app's Spanish-first default rather than silently falling back to
     * English outside a request context.
     */
    public function preferredLocale(): string
    {
        return 'es';
    }

    /**
     * Get the properties created by this user.
     *
     * @return HasMany<Property, $this>
     */
    public function createdProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'created_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()->whereNot('status', SubscriptionStatus::Canceled)->first();
    }

    public function isOnIndividualTrial(): bool
    {
        return $this->individual_trial_ends_at?->isFuture() === true
            && $this->activeSubscription() === null;
    }

    public function currentIndividualPlan(): ?SubscriptionPlan
    {
        if ($subscription = $this->activeSubscription()) {
            return $subscription->plan;
        }

        return $this->isOnIndividualTrial()
            ? SubscriptionPlan::query()->entryTierFor(SubscriptionLadder::Individual)->first()
            : null;
    }

    public function canPublishAnotherIndividualListing(): bool
    {
        $plan = $this->currentIndividualPlan();

        if ($plan === null) {
            return false;
        }

        return $plan->active_listings_limit === null
            || $this->createdProperties()->whereNull('team_id')->where('status', ListingStatus::Published)->count() < $plan->active_listings_limit;
    }

    /**
     * Photos uploaded via the listing wizard before the listing is saved.
     * Moved onto the property's `photos` collection once the listing is created/updated.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pending-listing-photos');
    }

    /**
     * Get the external identities connected to this user.
     *
     * @return HasMany<OauthIdentity, $this>
     */
    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OauthIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'renter_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /** @return HasMany<ModerationStrike, $this> */
    public function moderationStrikes(): HasMany
    {
        return $this->hasMany(ModerationStrike::class);
    }

    public function propertyFavorites(): HasMany
    {
        return $this->hasMany(PropertyFavorite::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'individual_trial_ends_at' => 'datetime',
            'is_admin' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * A suspended account keeps all of its data but is locked out.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
