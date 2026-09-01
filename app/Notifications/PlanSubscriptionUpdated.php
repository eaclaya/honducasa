<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanSubscriptionUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $planName,
        public string $targetUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your plan is now :planName', ['planName' => $this->planName]))
            ->line(__('Your subscription plan has been updated to :planName.', ['planName' => $this->planName]))
            ->action(__('Manage your plan'), $this->targetUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_label' => __('Subscription updated'),
            'property_name' => $this->planName,
            'preview' => __('Your subscription plan has been updated to :planName.', ['planName' => $this->planName]),
            'target_url' => $this->targetUrl,
        ];
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }
}
