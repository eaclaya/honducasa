<?php

namespace App\Models;

use Database\Factories\ConversationReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'reporter_id', 'reason', 'details', 'status'])]
class ConversationReport extends Model
{
    /** @use HasFactory<ConversationReportFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
