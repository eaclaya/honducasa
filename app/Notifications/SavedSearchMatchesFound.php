<?php

namespace App\Notifications;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SavedSearchMatchesFound extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SavedSearch $savedSearch, public int $matchCount) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_label' => app()->getLocale() === 'es' ? 'Alerta de búsqueda' : 'Search alert',
            'property_name' => $this->savedSearch->name,
            'preview' => app()->getLocale() === 'es'
                ? "{$this->matchCount} propiedades nuevas coinciden con tu búsqueda."
                : "{$this->matchCount} new properties match your search.",
            'target_url' => route('rentals.index', $this->savedSearch->filters, false),
        ];
    }

    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }
}
