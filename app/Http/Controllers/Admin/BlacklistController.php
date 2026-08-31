<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UnblockBlacklistedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnblockBlacklistedUserRequest;
use App\Models\ModerationStrike;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlacklistController extends Controller
{
    public function __construct(private UnblockBlacklistedUser $unblockBlacklistedUser) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        $users = User::query()
            ->whereNotNull('suspended_at')
            ->whereHas('moderationStrikes', fn (Builder $query) => $query->active())
            ->withCount([
                'moderationStrikes as active_strikes_count' => fn (Builder $query) => $query->active(),
                'moderationStrikes as total_strikes_count',
            ])
            ->with(['moderationStrikes' => fn ($query) => $query->active()->latest()])
            ->when($search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $match) => $match
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->latest('suspended_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'blockedAt' => $user->suspended_at?->translatedFormat('d M Y, H:i'),
                'blockedReason' => $user->suspension_reason,
                'activeStrikesCount' => $user->active_strikes_count,
                'totalStrikesCount' => $user->total_strikes_count,
                'strikes' => $user->moderationStrikes->map(fn (ModerationStrike $strike) => [
                    'id' => $strike->id,
                    'source' => $strike->source,
                    'reason' => $strike->reason,
                    'metadata' => $strike->metadata,
                    'createdAt' => $strike->created_at->translatedFormat('d M Y, H:i'),
                ])->values(),
            ]);

        return Inertia::render('admin/blacklist/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function destroy(UnblockBlacklistedUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isSuspended() && $user->moderationStrikes()->active()->exists(), 404);

        $this->unblockBlacklistedUser->handle(
            $request->user(),
            $user,
            $request->validated('reason'),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('The account was removed from the blacklist.'),
        ]);
    }
}
