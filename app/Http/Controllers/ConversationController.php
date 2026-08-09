<?php

namespace App\Http\Controllers;

use App\Actions\NotifyConversationParticipants;
use App\Enums\ConversationStatus;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        Gate::authorize('viewAny', Conversation::class);

        /** @var User $user */
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');
        $conversations = Conversation::query()
            ->select(['id', 'property_id', 'team_id', 'renter_id', 'status', 'blocked_by', 'last_message_at'])
            ->with(['property:id,name,slug,price_amount,currency,listing_type', 'property.media', 'team:id,name', 'renter:id,name'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->where(fn ($query) => $query->where('renter_id', $user->id)->orWhereIn('team_id', $teamIds))
            ->latest('last_message_at')
            ->get();

        $selectedConversation = $conversation ?? $conversations->first();
        if ($selectedConversation) {
            Gate::authorize('view', $selectedConversation);
            $selectedConversation->load(['property:id,name,slug,price_amount,currency,listing_type', 'property.media', 'team:id,name', 'renter:id,name', 'messages:id,conversation_id,sender_id,body,read_at,created_at']);
            $selectedConversation->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return Inertia::render('messages/Index', [
            'conversations' => $conversations->map(fn (Conversation $item) => $this->summary($item, $user->id)),
            'selected' => $selectedConversation ? $this->detail($selectedConversation, $user->id) : null,
        ]);
    }

    public function store(StoreConversationRequest $request, Property $property, NotifyConversationParticipants $notify): RedirectResponse
    {
        $conversation = DB::transaction(function () use ($request, $property, $notify): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['property_id' => $property->id, 'renter_id' => $request->user()->id],
                ['team_id' => $property->team_id, 'last_message_at' => now()],
            );
            $message = $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $request->validated('body')]);
            $conversation->update(['last_message_at' => now()]);
            $notify->handle($conversation, $message);

            return $conversation;
        });

        return to_route('messages.show', $conversation);
    }

    private function summary(Conversation $conversation, int $userId): array
    {
        return [
            'id' => $conversation->id,
            'propertyName' => $conversation->property->name,
            'propertyImage' => $conversation->property->getFirstMediaUrl('photos', 'thumb') ?: null,
            'counterpart' => $conversation->renter_id === $userId ? $conversation->team->name : $conversation->renter->name,
            'unreadCount' => $conversation->getAttribute('unread_count') ?? 0,
            'lastMessageAt' => $conversation->last_message_at?->diffForHumans(),
            'status' => $conversation->status->value,
            'canUnblock' => $conversation->status === ConversationStatus::Blocked
                && $conversation->blocked_by === $userId,
        ];
    }

    private function detail(Conversation $conversation, int $userId): array
    {
        return [
            ...$this->summary($conversation, $userId),
            'isRenter' => $conversation->renter_id === $userId,
            'propertySlug' => $conversation->property->slug,
            'propertyPrice' => $conversation->property->price_amount,
            'propertyCurrency' => $conversation->property->currency,
            'listingType' => $conversation->property->listing_type->value,
            'messages' => $conversation->messages->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'isMine' => $message->sender_id === $userId,
                'sentAt' => $message->created_at->format('H:i'),
            ]),
        ];
    }
}
