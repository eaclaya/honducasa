<?php

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewAccountRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public User $registeredUser, public string $registrationMethod) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => 'admin.user_registered',
            'sender_label' => __('New account registered'),
            'property_name' => $this->registeredUser->name,
            'preview' => __(':name created an account using :method.', [
                'name' => $this->registeredUser->name,
                'method' => __($this->registrationMethod === 'google' ? 'Google' : 'email'),
            ]),
            'target_url' => route('admin.users.index', ['search' => $this->registeredUser->email], false),
            'user_id' => $this->registeredUser->id,
            'registration_method' => $this->registrationMethod,
        ];
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }
}
