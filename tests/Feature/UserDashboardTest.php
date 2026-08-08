<?php

use App\Enums\TeamRole;
use App\Models\Property;
use App\Models\PropertyFavorite;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('user.dashboard'))->assertRedirect(route('login'));
});

test('team-less users see the user dashboard', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();
    PropertyFavorite::factory()->for($user)->for($property)->create();

    $response = $this->actingAs($user)->get(route('user.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UserDashboard')
        ->where('metrics.favorites', 1)
        ->where('metrics.savedSearches', 0)
        ->where('metrics.activeConversations', 0)
        ->has('recentConversations', 0),
    );
});

test('users with a team are redirected to their team dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('the user dashboard lists pending team invitations', function () {
    $owner = User::factory()->withPersonalTeam()->create(['name' => 'Team Owner']);
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Inviting Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->actingAs($user)->get(route('user.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UserDashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.team.name', 'Inviting Team'),
    );
});

test('an invited user who accepts ends up with only the invited team', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->actingAs($user)
        ->post(route('invitations.accept', $invitation));

    $response->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

    expect($user->fresh()->teams()->count())->toBe(1)
        ->and($user->fresh()->current_team_id)->toEqual($team->id);
});

test('declining an invitation returns a team-less user to the user dashboard', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $user = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->delete(route('invitations.decline', $invitation))
        ->assertRedirect(route('user.dashboard'));

    expect($user->fresh()->teams()->count())->toBe(0);
});

test('creating a team with publish intent forwards to listing creation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('teams.store'), [
        'name' => 'My Rentals',
        'publish' => '1',
    ]);

    $team = $user->fresh()->currentTeam;

    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('My Rentals');

    $response->assertRedirect(route('listings.create', ['current_team' => $team->slug]));
});
