<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Enums\ListingStatus;
use App\Models\Message;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_if($team === null, 403);

        $pendingInvitations = $request->user()->pendingTeamInvitations()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'metrics' => [
                'totalListings' => $team->properties()->count(),
                'publishedListings' => $team->properties()->where('status', ListingStatus::Published)->count(),
                'draftListings' => $team->properties()->where('status', ListingStatus::Draft)->count(),
                'activeConversations' => $team->conversations()->where('status', ConversationStatus::Active)->count(),
                'unreadMessages' => Message::query()->whereHas('conversation', fn ($query) => $query->where('team_id', $team->id))
                    ->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->count(),
            ],
            'recentConversations' => $team->conversations()
                ->with(['property:id,name,slug', 'renter:id,name'])
                ->latest('last_message_at')->limit(6)->get()
                ->map(fn ($conversation) => [
                    'id' => $conversation->id,
                    'propertyName' => $conversation->property->name,
                    'propertySlug' => $conversation->property->slug,
                    'renterName' => $conversation->renter->name,
                    'status' => $conversation->status->value,
                    'lastMessageAt' => $conversation->last_message_at?->diffForHumans(),
                ]),
        ]);
    }
}
