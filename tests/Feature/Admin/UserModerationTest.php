<?php

use App\Models\AdminActivity;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the users index lists accounts with role facets and supports search', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $landlord = User::factory()->withPersonalTeam()->create(['name' => 'Ana Lopez']);
    User::factory()->create(['name' => 'Carlos Mejia']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'Ana']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $landlord->id)
            ->where('facetCounts.all', 3));
});

test('an admin can suspend a user with a reason, which is recorded on the audit trail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.suspension.update', $user), [
            'suspended' => true,
            'reason' => 'Suspicious activity',
        ])
        ->assertRedirect();

    expect($user->fresh()->isSuspended())->toBeTrue()
        ->and($user->fresh()->suspension_reason)->toBe('Suspicious activity');

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->admin_id)->toBe($admin->id)
        ->and($activity->action)->toBe('user.suspended')
        ->and($activity->subject->is($user))->toBeTrue()
        ->and($activity->reason)->toBe('Suspicious activity');
});

test('suspending a user requires a reason', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.suspension.update', $user), ['suspended' => true])
        ->assertInvalid(['reason']);

    expect($user->fresh()->isSuspended())->toBeFalse();
});

test('an admin can reinstate a suspended user without a reason', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['suspended_at' => now(), 'suspension_reason' => 'Spam']);

    $this->actingAs($admin)
        ->patch(route('admin.users.suspension.update', $user), ['suspended' => false])
        ->assertRedirect();

    expect($user->fresh()->isSuspended())->toBeFalse()
        ->and($user->fresh()->suspension_reason)->toBeNull();
});

test('an admin cannot suspend their own account', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.suspension.update', $admin), [
            'suspended' => true,
            'reason' => 'Testing',
        ])
        ->assertForbidden();

    expect($admin->fresh()->isSuspended())->toBeFalse();
});

test('an admin can grant admin access to another user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.admin-status.update', $user), ['is_admin' => true])
        ->assertRedirect();

    expect($user->fresh()->is_admin)->toBeTrue();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('user.admin_granted');
});

test('an admin cannot remove their own admin access', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.admin-status.update', $admin), ['is_admin' => false])
        ->assertForbidden();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('an admin can remove another admin\'s access', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.admin-status.update', $otherAdmin), ['is_admin' => false])
        ->assertRedirect();

    expect($otherAdmin->fresh()->is_admin)->toBeFalse();
});

test('the renter facet counts users with at least one conversation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $renter = User::factory()->create();
    Conversation::factory()->create(['renter_id' => $renter->id]);
    User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'renter']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $renter->id));
});
