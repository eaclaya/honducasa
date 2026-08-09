<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
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
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'is_admin'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasMedia, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the properties created by this user.
     *
     * @return HasMany<Property, $this>
     */
    public function createdProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'created_by');
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
            'is_admin' => 'boolean',
        ];
    }
}
