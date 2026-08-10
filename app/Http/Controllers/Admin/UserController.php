<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAdminActivity;
use App\Actions\Admin\SetUserAdminStatus;
use App\Actions\Admin\SetUserSuspension;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UpdateUserAdminStatusRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;
        $role = $request->string('role')->toString() ?: null;
        $verification = $request->string('verification')->toString() ?: null;
        $registered = $request->string('registered')->toString() ?: null;
        $sort = $request->string('sort')->toString() ?: null;

        $users = User::query()
            ->withCount(['teams', 'conversations', 'propertyFavorites'])
            ->when($search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($role === 'landlord', fn (Builder $query) => $query->has('teams'))
            ->when($role === 'renter', fn (Builder $query) => $query->has('conversations'))
            ->when($role === 'admin', fn (Builder $query) => $query->where('is_admin', true))
            ->when($verification === 'verified', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($verification === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($registered === '7d', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->when($registered === '30d', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->when($sort === 'conversations', fn (Builder $query) => $query->orderByDesc('conversations_count'))
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when(! in_array($sort, ['conversations', 'name'], true), fn (Builder $query) => $query->latest())
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->email_verified_at !== null,
                'isAdmin' => $user->is_admin,
                'isLandlord' => $user->teams_count > 0,
                'isRenter' => $user->conversations_count > 0,
                'isSuspended' => $user->isSuspended(),
                'suspensionReason' => $user->suspension_reason,
                'teamsCount' => $user->teams_count,
                'conversationsCount' => $user->conversations_count,
                'favoritesCount' => $user->property_favorites_count,
                'createdAt' => $user->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'facetCounts' => [
                'all' => User::query()->count(),
                'landlord' => User::query()->has('teams')->count(),
                'renter' => User::query()->has('conversations')->count(),
                'admin' => User::query()->where('is_admin', true)->count(),
            ],
            'filters' => compact('search', 'role', 'verification', 'registered', 'sort'),
        ]);
    }

    public function updateSuspension(SuspendUserRequest $request, User $user): RedirectResponse
    {
        $suspended = $request->boolean('suspended');

        abort_unless(SetUserSuspension::allowedFor($request->user(), $user, $suspended), 403);

        $from = $user->isSuspended();
        app(SetUserSuspension::class)->handle($user, $suspended, $request->validated('reason'));
        app(RecordAdminActivity::class)->handle(
            $request->user(),
            $suspended ? 'user.suspended' : 'user.reinstated',
            $user,
            $request->validated('reason'),
            ['suspended' => ['from' => $from, 'to' => $suspended]],
        );

        return back();
    }

    public function updateAdminStatus(UpdateUserAdminStatusRequest $request, User $user): RedirectResponse
    {
        $isAdmin = $request->boolean('is_admin');

        abort_unless(SetUserAdminStatus::allowedFor($request->user(), $user, $isAdmin), 403);

        $from = $user->is_admin;
        app(SetUserAdminStatus::class)->handle($user, $isAdmin);
        app(RecordAdminActivity::class)->handle(
            $request->user(),
            $isAdmin ? 'user.admin_granted' : 'user.admin_revoked',
            $user,
            changes: ['is_admin' => ['from' => $from, 'to' => $isAdmin]],
        );

        return back();
    }
}
