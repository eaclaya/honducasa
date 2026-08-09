<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ConversationMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversationId,
        public string $propertyName,
        public string $senderLabel,
        public string $preview,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'property_name' => $this->propertyName,
            'sender_label' => $this->senderLabel,
            'preview' => $this->preview,
        ];
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }
}
