<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An append-only audit entry for a moderation action or impersonation session.
 *
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $reason
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User $admin
 * @property-read Model|null $subject
 */
#[Fillable(['admin_id', 'action', 'subject_type', 'subject_id', 'reason', 'changes', 'ip_address'])]
class AdminActivity extends Model
{
    /** Audit entries are written once and never touched again. */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
