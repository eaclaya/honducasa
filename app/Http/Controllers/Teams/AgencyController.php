<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\SubscribeToPlan;
use App\Enums\SubscriptionLadder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreAgencyRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('agencies/Create', [
            'plans' => SubscriptionPlan::query()
                ->where('ladder', SubscriptionLadder::Agency)
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

    public function store(
        StoreAgencyRequest $request,
        CreateTeam $createTeam,
        SubscribeToPlan $subscribeToPlan,
    ): RedirectResponse {
        $plan = SubscriptionPlan::query()->findOrFail($request->validated('subscription_plan_id'));

        $agency = DB::transaction(function () use ($request, $createTeam, $subscribeToPlan, $plan) {
            $agency = $createTeam->handle(
                $request->user(),
                $request->validated('name'),
                isPersonal: false,
            );

            $subscribeToPlan->handle($agency, $plan);

            return $agency;
        });

        return to_route('dashboard', ['current_team' => $agency->slug])
            ->with('toast', [
                'type' => 'success',
                'message' => __('Your agency was created successfully.'),
            ]);
    }
}
