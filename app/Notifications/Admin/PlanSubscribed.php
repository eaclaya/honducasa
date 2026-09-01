<?php

namespace App\Notifications\Admin;

use App\Models\TeamSubscription;
use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PlanSubscribed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamSubscription|UserSubscription $subscription) {}

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
        $plan = $this->subscription->plan;
        $subscriber = $this->subscription instanceof TeamSubscription
            ? $this->subscription->team
            : $this->subscription->user;
        $isTeamSubscription = $this->subscription instanceof TeamSubscription;

        return [
            'event_type' => 'admin.subscription_activated',
            'sender_label' => __('New subscription activated'),
            'property_name' => $subscriber->name,
            'preview' => __(':team subscribed to :plan.', [
                'team' => $subscriber->name,
                'plan' => $plan->name,
            ]),
            'target_url' => $isTeamSubscription
                ? route('admin.teams.index', ['search' => $subscriber->slug], false)
                : route('admin.users.index', ['search' => $subscriber->email], false),
            'team_id' => $isTeamSubscription ? $subscriber->id : null,
            'user_id' => $isTeamSubscription ? null : $subscriber->id,
            'subscription_plan_id' => $plan->id,
            'subscription_ladder' => $plan->ladder->value,
            'price_amount' => $plan->price_amount,
            'currency' => $plan->currency,
        ];
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }
}
