<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAdminActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanActiveRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('ladder')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'ladder' => $plan->ladder->value,
                'name' => $plan->name,
                'activeListingsLimit' => $plan->active_listings_limit,
                'seatsLimit' => $plan->seats_limit,
                'featuredListingSlots' => $plan->featured_listing_slots,
                'analyticsTier' => $plan->analytics_tier->value,
                'supportTier' => $plan->support_tier->value,
                'priceAmount' => $plan->price_amount,
                'currency' => $plan->currency,
                'provider' => $plan->provider->value,
                'isEntryTier' => $plan->is_entry_tier,
                'isActive' => $plan->is_active,
                'sortOrder' => $plan->sort_order,
                'subscribersCount' => $plan->subscriptions_count,
            ]);

        return Inertia::render('admin/subscription-plans/Index', ['plans' => $plans]);
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $plan = SubscriptionPlan::query()->create($request->validated());

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'subscription_plan.created',
            $plan,
        );

        return back();
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $fields = array_keys($request->validated());
        $normalize = fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value;
        $before = array_map($normalize, $subscriptionPlan->only($fields));

        $subscriptionPlan->update($request->validated());
        $subscriptionPlan->refresh();

        $after = array_map($normalize, $subscriptionPlan->only($fields));
        $changes = [];
        foreach ($fields as $field) {
            if ($before[$field] !== $after[$field]) {
                $changes[$field] = ['from' => $before[$field], 'to' => $after[$field]];
            }
        }

        app(RecordAdminActivity::class)->handle(
            $request->user(),
            'subscription_plan.updated',
            $subscriptionPlan,
            null,
            $changes,
        );

        return back();
    }

    public function updateActive(UpdateSubscriptionPlanActiveRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $from = $subscriptionPlan->is_active;

        $subscriptionPlan->update(['is_active' => $isActive]);
        app(RecordAdminActivity::class)->handle(
            $request->user(),
            $isActive ? 'subscription_plan.reactivated' : 'subscription_plan.retired',
            $subscriptionPlan,
            $request->validated('reason'),
            ['is_active' => ['from' => $from, 'to' => $isActive]],
        );

        return back();
    }
}
