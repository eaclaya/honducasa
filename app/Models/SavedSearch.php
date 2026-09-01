<?php

namespace App\Models;

use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'filters', 'fingerprint', 'alerts_enabled', 'last_notified_at'])]
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['filters' => 'array', 'alerts_enabled' => 'boolean', 'last_notified_at' => 'datetime'];
    }
}
