<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CancelTeamSubscription;
use App\Actions\Admin\CompTeamSubscription;
use App\Actions\Admin\ExtendTeamTrial;
use App\Actions\Admin\RecordAdminActivity;
use App\Actions\Admin\SetTeamSuspension;
use App\Enums\ListingStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelTeamSubscriptionRequest;
use App\Http\Requests\Admin\CompTeamSubscriptionRequest;
use App\Http\Requests\Admin\ExtendTeamTrialRequest;
use App\Http\Requests\Admin\SuspendTeamRequest;
use App\Models\SubscriptionPlan;
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
            ->with(['subscriptions' => fn (HasMany $query) => $query->whereNot('status', SubscriptionStatus::Canceled)->with('plan')])
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
                'subscription' => $this->subscriptionSummary($team),
            ]);

        return Inertia::render('admin/teams/Index', [
            'teams' => $teams,
            'subscriptionPlans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('ladder')
                ->orderBy('sort_order')
                ->get(['id', 'key', 'ladder', 'name']),
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

    public function extendTrial(ExtendTeamTrialRequest $request, Team $team): RedirectResponse
    {
        $days = $request->integer('days');
        $from = $team->trial_ends_at;

        $team = app(ExtendTeamTrial::class)->handle($team, $days);

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'team.trial_extended',
            $team,
            $request->validated('reason'),
            ['trial_ends_at' => ['from' => $from?->toIso8601String(), 'to' => $team->trial_ends_at->toIso8601String()]],
        );

        return back();
    }

    public function compSubscription(CompTeamSubscriptionRequest $request, Team $team): RedirectResponse
    {
        abort_if($team->activeSubscription() !== null, 422, 'This team already has an active subscription.');

        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $subscription = app(CompTeamSubscription::class)->handle($team, $plan);

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'team.subscription_comped',
            $team,
            $request->validated('reason'),
            ['subscription_plan_id' => $plan->id, 'team_subscription_id' => $subscription->id],
        );

        return back();
    }

    public function cancelSubscription(CancelTeamSubscriptionRequest $request, Team $team): RedirectResponse
    {
        $subscription = app(CancelTeamSubscription::class)->handle($team);

        abort_if($subscription === null, 404);

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'team.subscription_canceled',
            $team,
            $request->validated('reason'),
        );

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionSummary(Team $team): array
    {
        $subscription = $team->subscriptions->first();

        if ($subscription) {
            return [
                'state' => $subscription->status->value,
                'planName' => $subscription->plan->name,
            ];
        }

        if ($team->trial_ends_at?->isFuture()) {
            return ['state' => 'trial', 'trialEndsAt' => $team->trial_ends_at->translatedFormat('d M Y')];
        }

        if ($team->trial_ends_at !== null) {
            return ['state' => 'trial_expired'];
        }

        return ['state' => 'legacy'];
    }
}
