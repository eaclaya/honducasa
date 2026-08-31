<?php

namespace App\Models;

use Database\Factories\ModerationStrikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $source
 * @property string $reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $cleared_at
 * @property int|null $cleared_by
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read User|null $clearedBy
 */
#[Fillable(['user_id', 'source', 'reason', 'metadata', 'cleared_at', 'cleared_by'])]
class ModerationStrike extends Model
{
    /** @use HasFactory<ModerationStrikeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    /** @param Builder<ModerationStrike> $query */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('cleared_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'cleared_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
