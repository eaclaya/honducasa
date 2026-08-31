<?php

use App\Actions\Moderation\RecordModerationStrike;
use App\Models\AdminActivity;
use App\Models\ModerationStrike;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the third active moderation strike blocks the account', function () {
    $user = User::factory()->create();
    $strikes = app(RecordModerationStrike::class);

    $strikes->handle($user, 'listing_text', 'First violation');
    $strikes->handle($user, 'listing_image', 'Second violation');

    expect($user->fresh()->isSuspended())->toBeFalse();

    $strikes->handle($user, 'listing_text', 'Third violation');

    expect($user->fresh()->isSuspended())->toBeTrue()
        ->and($user->fresh()->suspension_reason)
        ->toBe('Automatically blocked after three content moderation violations.')
        ->and($user->moderationStrikes()->active()->count())->toBe(3);
});

test('a blacklisted account cannot log in', function () {
    $user = User::factory()->create();

    ModerationStrike::factory()->count(2)->for($user)->create();
    app(RecordModerationStrike::class)->handle($user, 'listing_image', 'Third violation');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an existing session is invalidated after the account is blacklisted', function () {
    $user = User::factory()->create();
    ModerationStrike::factory()->count(2)->for($user)->create();
    app(RecordModerationStrike::class)->handle($user, 'listing_image', 'Third violation');

    $this->actingAs($user->fresh())
        ->get(route('user.dashboard'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('admins can see blacklisted users and their strike reasons', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Blocked Publisher',
        'suspended_at' => now(),
        'suspension_reason' => 'Automatically blocked after three content moderation violations.',
    ]);
    ModerationStrike::factory()->count(3)->for($user)->create();

    $this->actingAs($admin)
        ->get(route('admin.blacklist.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/blacklist/Index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $user->id)
            ->where('users.data.0.activeStrikesCount', 3)
            ->has('users.data.0.strikes', 3));
});

test('non administrators cannot view the blacklist', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.blacklist.index'))
        ->assertForbidden();
});

test('an admin can unblock an account while preserving cleared strike history', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'suspended_at' => now(),
        'suspension_reason' => 'Automatically blocked after three content moderation violations.',
    ]);
    ModerationStrike::factory()->count(3)->for($user)->create();

    $this->actingAs($admin)
        ->delete(route('admin.blacklist.destroy', $user), [
            'reason' => 'Reviewed the evidence and approved the appeal.',
        ])
        ->assertRedirect();

    expect($user->fresh()->isSuspended())->toBeFalse()
        ->and($user->moderationStrikes()->count())->toBe(3)
        ->and($user->moderationStrikes()->active()->count())->toBe(0)
        ->and($user->moderationStrikes()->where('cleared_by', $admin->id)->count())->toBe(3);

    $activity = AdminActivity::query()->latest()->firstOrFail();

    expect($activity->action)->toBe('user.blacklist_unblocked')
        ->and($activity->reason)->toBe('Reviewed the evidence and approved the appeal.');
});

test('unblocking a blacklisted account requires a reason', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['suspended_at' => now()]);
    ModerationStrike::factory()->count(3)->for($user)->create();

    $this->actingAs($admin)
        ->delete(route('admin.blacklist.destroy', $user))
        ->assertInvalid('reason');

    expect($user->fresh()->isSuspended())->toBeTrue()
        ->and($user->moderationStrikes()->active()->count())->toBe(3);
});
