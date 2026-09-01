<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Admin\NewAccountRegistered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen includes team invitation context', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Laravel Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('teamInvitation.code', $invitation->code)
        ->where('teamInvitation.teamName', 'Laravel Team'),
    );
});

test('new users can register without getting a team', function () {
    Notification::fake();
    $administrator = User::factory()->create(['is_admin' => true]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user->teams()->count())->toBe(0);
    expect($user->current_team_id)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertSentTo(
        $administrator,
        NewAccountRegistered::class,
        fn (NewAccountRegistered $notification) => $notification->registeredUser->is($user)
            && $notification->registrationMethod === 'email',
    );
    $response->assertRedirect('/dashboard');
});

test('a registration carrying a redirect field returns there instead of the dashboard', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'redirecting@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'redirect' => '/properties/nice-house',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/properties/nice-house');
});

test('a registration redirect field pointing off-site is ignored', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'safe@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'redirect' => 'https://evil.example.com',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});
