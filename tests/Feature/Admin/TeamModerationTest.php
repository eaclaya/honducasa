<?php

use App\Models\AdminActivity;
use App\Models\Property;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the teams index hides soft-deleted teams unless requested', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $active = Team::factory()->create();
    $deleted = Team::factory()->trashed()->create();

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams.data', 1)
            ->where('teams.data.0.id', $active->id));

    $this->actingAs($admin)
        ->get(route('admin.teams.index', ['show_deleted' => true]))
        ->assertInertia(fn (Assert $page) => $page->has('teams.data', 2));

    expect($deleted->trashed())->toBeTrue();
});

test('an admin can suspend and reinstate a team with a reason recorded', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.teams.suspension.update', $team), [
            'suspended' => true,
            'reason' => 'Repeated listing violations',
        ])
        ->assertRedirect();

    expect($team->fresh()->isSuspended())->toBeTrue();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.suspended')
        ->and($activity->subject->is($team))->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.teams.suspension.update', $team), ['suspended' => false])
        ->assertRedirect();

    expect($team->fresh()->isSuspended())->toBeFalse();
});

test('an admin can restore a soft-deleted team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->trashed()->create();

    $this->actingAs($admin)
        ->patch(route('admin.teams.restore', $team))
        ->assertRedirect();

    expect($team->fresh()->trashed())->toBeFalse();

    $activity = AdminActivity::query()->latest()->first();
    expect($activity->action)->toBe('team.restored');
});

test('the teams index reports property counts per team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    Property::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'published']);

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teams.data.0.propertiesCount', 2)
            ->where('teams.data.0.publishedPropertiesCount', 2));
});
