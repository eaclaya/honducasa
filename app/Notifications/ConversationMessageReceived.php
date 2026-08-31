<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message about :propertyName', ['propertyName' => $this->propertyName]))
            ->line(__(':senderLabel sent you a message about :propertyName.', [
                'senderLabel' => $this->senderLabel,
                'propertyName' => $this->propertyName,
            ]))
            ->line($this->preview)
            ->action(__('View conversation'), route('messages.show', $this->conversationId));
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
