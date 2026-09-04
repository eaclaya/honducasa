<?php

use App\Enums\AnalyticsTier;
use App\Enums\PricingModel;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionProvider;
use App\Models\AdminActivity;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the subscription plans index lists the catalog grouped by ladder', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    SubscriptionPlan::factory()->create(['ladder' => 'individual', 'name' => 'Individual — Basic']);
    SubscriptionPlan::factory()->create(['ladder' => 'agency', 'name' => 'Agency — Starter']);

    $this->actingAs($admin)
        ->get(route('admin.subscription-plans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subscription-plans/Index')
            ->has('plans', 2));
});

test('an admin can retire and reactivate a plan, which is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.subscription-plans.active.update', $plan), ['is_active' => false])
        ->assertRedirect();

    expect($plan->fresh()->is_active)->toBeFalse();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('subscription_plan.retired')
        ->and($activity->subject->is($plan))->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.subscription-plans.active.update', $plan), ['is_active' => true])
        ->assertRedirect();

    expect($plan->fresh()->is_active)->toBeTrue();
});

test('an admin can create a new plan, which is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'key' => 'individual-pro',
            'ladder' => 'individual',
            'name' => 'Individual — Pro',
            'pricing_model' => 'tiered',
            'active_listings_limit' => 20,
            'seats_limit' => 1,
            'featured_listing_slots' => 2,
            'analytics_tier' => 'full',
            'support_tier' => 'priority',
            'price_amount' => 1_200,
            'currency' => 'HNL',
            'provider' => 'manual',
            'is_entry_tier' => false,
            'sort_order' => 3,
        ])
        ->assertRedirect();

    $plan = SubscriptionPlan::query()->where('key', 'individual-pro')->firstOrFail();
    expect($plan->name)->toBe('Individual — Pro')
        ->and($plan->price_amount)->toBe(1_200);

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('subscription_plan.created')
        ->and($activity->subject->is($plan))->toBeTrue();
});

test('creating a plan requires a unique key', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    SubscriptionPlan::factory()->create(['key' => 'individual-basic']);

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'key' => 'individual-basic',
            'ladder' => 'individual',
            'name' => 'Duplicate',
            'featured_listing_slots' => 0,
            'analytics_tier' => 'basic',
            'support_tier' => 'standard',
            'price_amount' => 100,
            'currency' => 'HNL',
            'provider' => 'manual',
        ])
        ->assertSessionHasErrors('key');
});

test('an admin can edit a plan’s metadata, which is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create([
        'name' => 'Individual — Basic',
        'active_listings_limit' => 3,
        'featured_listing_slots' => 0,
        'analytics_tier' => 'basic',
        'support_tier' => 'standard',
        'is_entry_tier' => false,
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.subscription-plans.update', $plan), [
            'name' => 'Individual — Basic+',
            'active_listings_limit' => 5,
            'seats_limit' => $plan->seats_limit,
            'featured_listing_slots' => 1,
            'analytics_tier' => 'full',
            'support_tier' => 'standard',
            'is_entry_tier' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $plan->refresh();
    expect($plan->name)->toBe('Individual — Basic+')
        ->and($plan->active_listings_limit)->toBe(5)
        ->and($plan->featured_listing_slots)->toBe(1)
        ->and($plan->analytics_tier)->toBe(AnalyticsTier::Full)
        ->and($plan->is_entry_tier)->toBeTrue();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('subscription_plan.updated')
        ->and($activity->subject->is($plan))->toBeTrue()
        ->and($activity->changes)->toHaveKeys(['name', 'active_listings_limit', 'featured_listing_slots', 'analytics_tier', 'is_entry_tier']);
});

test('editing a plan cannot change its price, provider, key or ladder', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->create([
        'key' => 'individual-basic',
        'ladder' => 'individual',
        'price_amount' => 350,
        'provider' => 'manual',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.subscription-plans.update', $plan), [
            'name' => $plan->name,
            'featured_listing_slots' => $plan->featured_listing_slots,
            'analytics_tier' => $plan->analytics_tier->value,
            'support_tier' => $plan->support_tier->value,
            'is_entry_tier' => $plan->is_entry_tier,
            'sort_order' => $plan->sort_order,
            'key' => 'individual-hacked',
            'ladder' => 'agency',
            'price_amount' => 999_999,
            'provider' => 'stripe',
        ])
        ->assertRedirect();

    $plan->refresh();
    expect($plan->key)->toBe('individual-basic')
        ->and($plan->ladder)->toBe(SubscriptionLadder::Individual)
        ->and($plan->price_amount)->toBe(350)
        ->and($plan->provider)->toBe(SubscriptionProvider::Manual);
});

test('a per-listing plan cannot be created with an active listings limit', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'key' => 'agency-per-listing',
            'ladder' => 'agency',
            'name' => 'Agency — Per listing',
            'pricing_model' => PricingModel::PerListing->value,
            'active_listings_limit' => 5,
            'featured_listing_slots' => 0,
            'analytics_tier' => 'basic',
            'support_tier' => 'standard',
            'price_amount' => 500,
            'currency' => 'HNL',
            'provider' => 'manual',
        ])
        ->assertSessionHasErrors('active_listings_limit');
});

test('a per-listing plan created without a limit is stored correctly', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.subscription-plans.store'), [
            'key' => 'agency-per-listing',
            'ladder' => 'agency',
            'name' => 'Agency — Per listing',
            'pricing_model' => PricingModel::PerListing->value,
            'featured_listing_slots' => 0,
            'analytics_tier' => 'basic',
            'support_tier' => 'standard',
            'price_amount' => 500,
            'currency' => 'HNL',
            'provider' => 'manual',
        ])
        ->assertRedirect();

    $plan = SubscriptionPlan::query()->where('key', 'agency-per-listing')->firstOrFail();
    expect($plan->pricing_model)->toBe(PricingModel::PerListing)
        ->and($plan->active_listings_limit)->toBeNull();
});

test('a per-listing plan cannot be updated to have an active listings limit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = SubscriptionPlan::factory()->perListing()->create();

    $this->actingAs($admin)
        ->patch(route('admin.subscription-plans.update', $plan), [
            'name' => $plan->name,
            'active_listings_limit' => 5,
            'featured_listing_slots' => $plan->featured_listing_slots,
            'analytics_tier' => $plan->analytics_tier->value,
            'support_tier' => $plan->support_tier->value,
            'sort_order' => $plan->sort_order,
        ])
        ->assertSessionHasErrors('active_listings_limit');
});
