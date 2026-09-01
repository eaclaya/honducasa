<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\SubscribeToPlan;
use App\Enums\SubscriptionLadder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SubscribeToPlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamBillingController extends Controller
{
    /**
     * Show the team's current plan and the plans available for its ladder.
     */
    public function edit(Team $team): Response
    {
        Gate::authorize('update', $team);

        $subscription = $team->activeSubscription();

        return Inertia::render('teams/Billing', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
            ],
            'currentPlanKey' => $team->currentPlan()?->key,
            'subscriptionStatus' => $subscription?->status->value,
            'isOnTrial' => $team->isOnTrial(),
            'trialEndsAt' => $team->trial_ends_at?->translatedFormat('d M Y'),
            'plans' => SubscriptionPlan::query()
                ->where('ladder', SubscriptionLadder::forTeam($team->is_personal))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id' => $plan->id,
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'activeListingsLimit' => $plan->active_listings_limit,
                    'seatsLimit' => $plan->seats_limit,
                    'featuredListingSlots' => $plan->featured_listing_slots,
                    'analyticsTier' => $plan->analytics_tier->value,
                    'supportTier' => $plan->support_tier->value,
                    'priceAmount' => $plan->price_amount,
                    'currency' => $plan->currency,
                    'isEntryTier' => $plan->is_entry_tier,
                ]),
        ]);
    }

    /**
     * Subscribe the team to a plan, replacing any live subscription it has.
     */
    public function update(SubscribeToPlanRequest $request, Team $team): RedirectResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($request->validated('subscription_plan_id'));

        app(SubscribeToPlan::class)->handle($team, $plan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }
}
