<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserDashboardController extends Controller
{
    /**
     * Show the team-less user dashboard, or forward landlords to their team dashboard.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->currentTeam) {
            return to_route('dashboard', ['current_team' => $user->currentTeam->slug]);
        }

        return Inertia::render('UserDashboard', [
            'pendingInvitations' => $user->pendingTeamInvitations()
                ->map(fn (TeamInvitation $invitation) => [
                    'code' => $invitation->code,
                    'inviterName' => $invitation->inviter->name,
                    'team' => [
                        'name' => $invitation->team->name,
                        'slug' => $invitation->team->slug,
                    ],
                ]),
            'metrics' => [
                'favorites' => $user->propertyFavorites()->count(),
                'savedSearches' => $user->savedSearches()->count(),
                'activeConversations' => $user->conversations()
                    ->where('status', ConversationStatus::Active)
                    ->count(),
            ],
            'recentConversations' => $user->conversations()
                ->with(['property:id,name,slug', 'team:id,name'])
                ->latest('last_message_at')
                ->limit(6)
                ->get()
                ->map(fn ($conversation) => [
                    'id' => $conversation->id,
                    'propertyName' => $conversation->property->name,
                    'propertySlug' => $conversation->property->slug,
                    'teamName' => $conversation->team->name,
                    'status' => $conversation->status->value,
                    'lastMessageAt' => $conversation->last_message_at?->diffForHumans(),
                ]),
        ]);
    }
}
