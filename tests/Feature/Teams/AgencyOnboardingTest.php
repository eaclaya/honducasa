<?php

use App\Enums\SubscriptionLadder;
use App\Enums\TeamRole;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Admin\PlanSubscribed;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('verified users can view active agency plans', function () {
    $user = User::factory()->create();
    $agencyPlan = SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Agency,
    ]);
    SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Individual,
    ]);
    SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Agency,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('agencies.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agencies/Create')
            ->has('plans', 1)
            ->where('plans.0.id', $agencyPlan->id));
});

test('guests and unverified users cannot start agency onboarding', function () {
    $this->get(route('agencies.create'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('agencies.create'))
        ->assertRedirect(route('verification.notice'));
});

test('an agency and its selected subscription are created atomically', function () {
    Notification::fake();
    $administrator = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->withPersonalTeam()->create();
    $personalTeam = $user->currentTeam;
    $plan = SubscriptionPlan::factory()->create([
        'ladder' => SubscriptionLadder::Agency,
    ]);

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            'name' => 'Koral Realty',
            'subscription_plan_id' => $plan->id,
        ])
        ->assertRedirect(route('dashboard', ['current_team' => 'koral-realty']))
        ->assertSessionHas('toast.type', 'success');

    $agency = Team::query()->where('slug', 'koral-realty')->sole();

    expect($agency->is_personal)->toBeFalse()
        ->and($agency->members()->whereKey($user->id)->first()?->pivot->role)
        ->toBe(TeamRole::Owner)
        ->and($agency->activeSubscription()?->subscription_plan_id)->toBe($plan->id)
        ->and($user->fresh()->current_team_id)->toBe($agency->id)
        ->and($personalTeam->fresh())->not->toBeNull();

    Notification::assertSentTo(
        $administrator,
        PlanSubscribed::class,
        fn (PlanSubscribed $notification) => $notification->subscription->team_id === $agency->id,
    );
});

test('individual and inactive plans cannot be used to create an agency', function (
    array $planAttributes,
) {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::factory()->create($planAttributes);

    $this->actingAs($user)
        ->post(route('agencies.store'), [
            'name' => 'Invalid Agency',
            'subscription_plan_id' => $plan->id,
        ])
        ->assertSessionHasErrors('subscription_plan_id');

    expect(Team::query()->where('name', 'Invalid Agency')->exists())->toBeFalse();
})->with([
    'individual plan' => [['ladder' => SubscriptionLadder::Individual]],
    'inactive agency plan' => [[
        'ladder' => SubscriptionLadder::Agency,
        'is_active' => false,
    ]],
]);

test('agency validation errors are translated into Spanish', function () {
    $user = User::factory()->create();

    $this->withSession(['locale' => 'es'])
        ->actingAs($user)
        ->post(route('agencies.store'))
        ->assertSessionHasErrors([
            'name' => 'El nombre de la agencia es obligatorio.',
            'subscription_plan_id' => 'Selecciona un plan para agencias.',
        ]);
});
