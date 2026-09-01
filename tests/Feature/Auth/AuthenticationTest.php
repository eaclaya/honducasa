<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login screen includes team invitation context', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Laravel Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this->get(route('login', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('teamInvitation.code', $invitation->code)
        ->where('teamInvitation.teamName', 'Laravel Team'),
    );
});

test('users with a team land on their team dashboard after login', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('team-less users land on the user dashboard after login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('a login carrying a redirect field returns there instead of the team dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/properties/nice-house',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/properties/nice-house');
});

test('a login redirect field pointing off-site is ignored', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => 'https://evil.example.com',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('a login redirect field using a protocol-relative URL is ignored', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '//evil.example.com',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('passkey login response redirects to the current team dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $request = Request::create(route('login', absolute: false), 'GET', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setLaravelSession($this->app['session.store']);
    $request->setUserResolver(fn () => $user);

    $jsonResponse = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($jsonResponse->getData()->redirect)->toBe(route('dashboard', ['current_team' => $user->personalTeam()->slug]));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('a suspended user cannot authenticate even with the correct password', function () {
    $user = User::factory()->create([
        'suspended_at' => now(),
        'suspension_reason' => 'Spam',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('a suspended user with two-factor enabled is rejected before the two-factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create([
        'suspended_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    $response->assertSessionMissing('login.id');
});

test('logging in a suspended user directly, as passkey login does, is rejected', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    expect(fn () => Auth::login($user))->toThrow(ValidationException::class);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('an authenticated user without a team is redirected off guest routes to the user dashboard', function () {
    $user = User::factory()->create();

    expect($user->currentTeam)->toBeNull()
        ->and($user->fallbackTeam())->toBeNull();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('user.dashboard'));
    $this->actingAs($user)->get(route('register'))->assertRedirect(route('user.dashboard'));
    $this->actingAs($user)
        ->get(route('auth.google.redirect'))
        ->assertRedirect(route('user.dashboard'));
});

test('an authenticated user with a team is redirected off guest routes to their team dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->fresh()->currentTeam;

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
});

test('an authenticated user whose current team was cleared falls back to a team they still belong to', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->fresh()->currentTeam;
    $user->forgetCurrentTeam();

    $this->actingAs($user->fresh())
        ->get(route('login'))
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
});
