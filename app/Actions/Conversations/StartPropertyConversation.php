<?php

namespace App\Actions\Conversations;

use App\Actions\NotifyConversationParticipants;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartPropertyConversation
{
    public function __construct(private NotifyConversationParticipants $notify) {}

    public function handle(User $renter, Property $property, string $body): bool
    {
        return DB::transaction(function () use ($renter, $property, $body): bool {
            $conversation = Conversation::query()->firstOrCreate(
                ['property_id' => $property->id, 'renter_id' => $renter->id],
                ['team_id' => $property->team_id, 'last_message_at' => now()],
            );

            if (! $conversation->wasRecentlyCreated) {
                return false;
            }

            /** @var Message $message */
            $message = $conversation->messages()->create([
                'sender_id' => $renter->id,
                'body' => $body,
            ]);
            $conversation->update(['last_message_at' => now()]);
            $this->notify->handle($conversation, $message);

            return true;
        });
    }
}
