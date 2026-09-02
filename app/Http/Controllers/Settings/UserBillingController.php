<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\SubscribeToPlan;
use App\Enums\SubscriptionLadder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SubscribeUserToPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserBillingController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        return Inertia::render('settings/Billing', [
            'currentPlanKey' => $user->currentIndividualPlan()?->key,
            'subscriptionStatus' => $subscription?->status->value,
            'isOnTrial' => $user->isOnIndividualTrial(),
            'trialEndsAt' => $user->individual_trial_ends_at?->translatedFormat('d M Y'),
            'plans' => SubscriptionPlan::query()
                ->where('ladder', SubscriptionLadder::Individual)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id' => $plan->id,
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'activeListingsLimit' => $plan->active_listings_limit,
                    'pricingModel' => $plan->pricing_model->value,
                    'priceAmount' => $plan->price_amount,
                    'currency' => $plan->currency,
                ]),
        ]);
    }

    public function update(SubscribeUserToPlanRequest $request, SubscribeToPlan $subscribeToPlan): RedirectResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($request->validated('subscription_plan_id'));
        $subscribeToPlan->handle($request->user(), $plan);

        return back()->with('toast', ['type' => 'success', 'message' => __('Plan updated.')]);
    }
}
