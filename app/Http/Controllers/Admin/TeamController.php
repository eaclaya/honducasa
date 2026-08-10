<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAdminActivity;
use App\Actions\Admin\SetTeamSuspension;
use App\Enums\ListingStatus;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendTeamRequest;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;
        $properties = $request->string('properties')->toString() ?: null;
        $sort = $request->string('sort')->toString() ?: null;
        $showDeleted = $request->boolean('show_deleted');

        $teams = Team::query()
            ->when($showDeleted, fn (Builder $query) => $query->withTrashed())
            ->withCount(['members', 'conversations', 'properties'])
            ->withCount(['properties as published_properties_count' => fn (Builder $query) => $query->where('status', ListingStatus::Published)])
            ->with(['memberships' => fn (HasMany $query) => $query->where('role', TeamRole::Owner->value)->with('user:id,name')])
            ->when($search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('members', fn (Builder $m) => $m->where('email', 'like', "%{$search}%"))
            ))
            ->when($type === 'organization', fn (Builder $query) => $query->where('is_personal', false))
            ->when($type === 'personal', fn (Builder $query) => $query->where('is_personal', true))
            ->when($properties === 'with', fn (Builder $query) => $query->has('properties'))
            ->when($properties === 'without', fn (Builder $query) => $query->doesntHave('properties'))
            ->when($sort === 'recent', fn (Builder $query) => $query->latest())
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when(! in_array($sort, ['recent', 'name'], true), fn (Builder $query) => $query->orderByDesc('properties_count'))
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Team $team) => [
                'id' => $team->id,
                'slug' => $team->slug,
                'name' => $team->name,
                'isPersonal' => $team->is_personal,
                'owner' => $team->memberships->first()?->user->name,
                'membersCount' => $team->members_count,
                'propertiesCount' => $team->properties_count,
                'publishedPropertiesCount' => $team->published_properties_count,
                'conversationsCount' => $team->conversations_count,
                'isSuspended' => $team->isSuspended(),
                'suspensionReason' => $team->suspension_reason,
                'deletedAt' => $team->deleted_at?->translatedFormat('d M Y'),
                'createdAt' => $team->created_at->translatedFormat('d M Y'),
            ]);

        return Inertia::render('admin/teams/Index', [
            'teams' => $teams,
            'filters' => compact('search', 'type', 'properties', 'sort', 'showDeleted'),
        ]);
    }

    public function updateSuspension(SuspendTeamRequest $request, Team $team): RedirectResponse
    {
        $suspended = $request->boolean('suspended');
        $from = $team->isSuspended();

        app(SetTeamSuspension::class)->handle($team, $suspended, $request->validated('reason'));
        app(RecordAdminActivity::class)->handle(
            $request->user(),
            $suspended ? 'team.suspended' : 'team.reinstated',
            $team,
            $request->validated('reason'),
            ['suspended' => ['from' => $from, 'to' => $suspended]],
        );

        return back();
    }

    public function restore(Request $request, Team $team): RedirectResponse
    {
        $team->restore();
        app(RecordAdminActivity::class)->handle($request->user(), 'team.restored', $team);

        return back();
    }
}
