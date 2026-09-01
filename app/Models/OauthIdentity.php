<?php

namespace App\Models;

use App\Enums\IdentityProvider;
use Database\Factories\OauthIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property IdentityProvider $provider
 * @property string $provider_subject
 * @property string|null $provider_email
 * @property Carbon $linked_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'provider', 'provider_subject', 'provider_email', 'linked_at', 'last_used_at'])]
class OauthIdentity extends Model
{
    /** @use HasFactory<OauthIdentityFactory> */
    use HasFactory;

    /**
     * Get the local user attached to the provider identity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => IdentityProvider::class,
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
