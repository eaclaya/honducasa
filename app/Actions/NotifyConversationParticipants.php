<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\ConversationMessageReceived;
use Illuminate\Support\Str;

class NotifyConversationParticipants
{
    public function handle(Conversation $conversation, Message $message): void
    {
        $conversation->loadMissing(['property:id,name,created_by,team_id', 'property.creator:id,name', 'team:id,name', 'team.members:id', 'renter:id']);

        if ($message->sender_id === $conversation->renter_id) {
            $recipients = $conversation->team?->members->where('id', '!=', $message->sender_id)
                ?? collect([$conversation->property->creator])->where('id', '!=', $message->sender_id);
            $senderLabel = app()->getLocale() === 'es' ? 'Persona interesada' : 'Prospective renter';
        } else {
            $recipients = collect([$conversation->renter])->where('id', '!=', $message->sender_id);
            $senderLabel = $conversation->team?->name ?? $conversation->property->creator->name;
        }

        foreach ($recipients as $recipient) {
            $recipient->notify((new ConversationMessageReceived(
                conversationId: $conversation->id,
                propertyName: $conversation->property->name ?? 'Honducasa',
                senderLabel: $senderLabel,
                preview: Str::limit($message->body, 120),
            ))->afterCommit());
        }
    }
}
