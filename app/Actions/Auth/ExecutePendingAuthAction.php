<?php

namespace App\Actions\Auth;

use App\Actions\Conversations\StartPropertyConversation;
use App\Actions\Properties\FavoriteProperty;
use App\Actions\SavedSearches\CreateSavedSearch;
use App\Enums\ConversationStatus;
use App\Enums\ListingStatus;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use App\Support\SafeRedirectPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExecutePendingAuthAction
{
    public function __construct(
        private FavoriteProperty $favoriteProperty,
        private CreateSavedSearch $createSavedSearch,
        private StartPropertyConversation $startPropertyConversation,
    ) {}

    public function handle(Request $request, User $user): ?string
    {
        $pendingAction = $request->session()->get('auth.pending_action');

        if (! is_array($pendingAction)) {
            return null;
        }

        try {
            match ($pendingAction['type'] ?? null) {
                'favorite_property' => $this->favoriteProperty->handle(
                    $user,
                    Property::query()->where('slug', data_get($pendingAction, 'payload.property_slug'))->firstOrFail(),
                ),
                'save_search' => $this->saveSearch($pendingAction, $user),
                'start_conversation' => $this->startConversation($pendingAction, $user),
                default => null,
            };
        } catch (Throwable $exception) {
            if (! $exception instanceof HttpExceptionInterface) {
                report($exception);
            }
        } finally {
            $request->session()->forget('auth.pending_action');
        }

        return SafeRedirectPath::resolve($pendingAction['redirect'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $pendingAction
     */
    private function saveSearch(array $pendingAction, User $user): void
    {
        $savedSearch = $this->createSavedSearch->handle(
            $user,
            data_get($pendingAction, 'payload.saved_search', []),
        );

        Inertia::flash('toast', $savedSearch->wasRecentlyCreated
            ? ['type' => 'success', 'message' => __('Search saved.')]
            : ['type' => 'info', 'message' => __('This search is already saved.')]);
    }

    /**
     * @param  array<string, mixed>  $pendingAction
     */
    private function startConversation(array $pendingAction, User $user): void
    {
        $property = Property::query()
            ->where('slug', data_get($pendingAction, 'payload.property_slug'))
            ->where('status', ListingStatus::Published)
            ->firstOrFail();

        Gate::forUser($user)->authorize('create', Conversation::class);

        abort_if($property->isOwnedBy($user), 403);

        $existingStatus = Conversation::query()
            ->where('property_id', $property->id)
            ->where('renter_id', $user->id)
            ->value('status');

        abort_unless(
            $existingStatus === null
                || $existingStatus === ConversationStatus::Active
                || $existingStatus === ConversationStatus::Active->value,
            403,
        );

        $messageSent = $this->startPropertyConversation->handle(
            $user,
            $property,
            data_get($pendingAction, 'payload.body'),
        );

        Inertia::flash('toast', $messageSent
            ? ['type' => 'success', 'message' => __('Message sent.')]
            : ['type' => 'info', 'message' => __('Conversation already started.')]);
    }
}
