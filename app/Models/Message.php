<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'sender_id', 'body', 'read_at', 'redacted_at', 'redacted_by'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * A redacted message is hidden from participants but keeps its `body` so it
     * survives as evidence for the report that triggered the redaction.
     */
    public function isRedacted(): bool
    {
        return $this->redacted_at !== null;
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'redacted_at' => 'datetime'];
    }
}
