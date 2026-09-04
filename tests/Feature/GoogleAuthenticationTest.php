<?php

use App\Enums\IdentityProvider;
use App\Enums\TeamRole;
use App\Models\OauthIdentity;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Admin\NewAccountRegistered;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Laravel\mock;

test('users can start Google authentication', function () {
    Socialite::fake('google');

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();
});

test('users can authenticate from the Google One Tap prompt', function () {
    mock(GoogleIdTokenVerifier::class)
        ->shouldReceive('verify')
        ->once()
        ->with('google-id-token')
        ->andReturn([
            'sub' => 'google-one-tap-subject',
            'name' => 'One Tap User',
            'email' => 'one-tap@example.com',
            'email_verified' => true,
        ]);

    $response = $this->post(route('auth.google.one-tap'), [
        'credential' => 'google-id-token',
        'redirect' => '/rentals?city=Tegucigalpa',
    ]);

    $user = User::query()->where('email', 'one-tap@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect('/rentals?city=Tegucigalpa');
    expect($user->hasVerifiedEmail())->toBeTrue();

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => IdentityProvider::Google->value,
        'provider_subject' => 'google-one-tap-subject',
    ]);
});

test('Google One Tap ignores an off-site return path', function () {
    mock(GoogleIdTokenVerifier::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn([
            'sub' => 'google-one-tap-safe-subject',
            'name' => 'Safe One Tap User',
            'email' => 'safe-one-tap@example.com',
            'email_verified' => true,
        ]);

    $this->post(route('auth.google.one-tap'), [
        'credential' => 'google-id-token',
        'redirect' => 'https://evil.example.com',
    ])->assertRedirect(route('user.dashboard'));
});

test('a verified Google identity creates and authenticates a user', function () {
    Notification::fake();
    $administrator = User::factory()->create(['is_admin' => true]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'name' => 'Google User',
        'email' => 'google@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'google@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('user.dashboard'));

    expect($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->teams()->count())->toBe(0)
        ->and($user->current_team_id)->toBeNull();

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => IdentityProvider::Google->value,
        'provider_subject' => 'google-subject-1',
    ]);

    Notification::assertSentTo(
        $administrator,
        NewAccountRegistered::class,
        fn (NewAccountRegistered $notification) => $notification->registeredUser->is($user)
            && $notification->registrationMethod === 'google',
    );
});

test('a returning Google identity authenticates the linked user without duplication', function () {
    Notification::fake();
    $administrator = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    OauthIdentity::factory()->for($user)->create([
        'provider_subject' => 'google-subject-2',
        'provider_email' => $user->email,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-2',
        'name' => $user->name,
        'email' => $user->email,
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(OauthIdentity::query()->count())->toBe(1);
    Notification::assertNotSentTo($administrator, NewAccountRegistered::class);
});

test('a suspended user cannot authenticate via Google', function () {
    $user = User::factory()->create(['suspended_at' => now()]);
    OauthIdentity::factory()->for($user)->create([
        'provider_subject' => 'google-subject-suspended',
        'provider_email' => $user->email,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-suspended',
        'name' => $user->name,
        'email' => $user->email,
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $this->assertGuest();
    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
});

test('Google claims a pre-provisioned password-less account by email instead of rejecting it', function () {
    $superadmin = User::factory()->withPersonalTeam()->create([
        'email' => 'claim-me@example.com',
        'password' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-claim',
        'name' => 'Real Superadmin',
        'email' => 'claim-me@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($superadmin);
    $response->assertRedirect();
    expect(User::query()->where('email', 'claim-me@example.com')->count())->toBe(1);

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $superadmin->id,
        'provider' => IdentityProvider::Google->value,
        'provider_subject' => 'google-subject-claim',
    ]);
});

test('Google does not silently link an existing manual account by email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-3',
        'email' => 'existing@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $this->assertGuest();
    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    expect(OauthIdentity::query()->count())->toBe(0);
});

test('Google identities without a verified email are rejected', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-4',
        'email' => 'unverified@example.com',
        'email_verified' => false,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $this->assertGuest();
    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    expect(User::query()->where('email', 'unverified@example.com')->exists())->toBeFalse();
});

test('Google signup accepts a pending team invitation', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = Team::factory()->create(['name' => 'Inviting Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited-google@example.com',
        'invited_by' => $owner->id,
    ]);

    Socialite::fake('google');
    $this->get(route('auth.google.redirect', ['invitation' => $invitation->code]));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-6',
        'name' => 'Invited Google User',
        'email' => 'invited-google@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'invited-google@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

    expect($user->teams()->count())->toBe(1)
        ->and($user->belongsToTeam($team))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('Google signup ignores an invitation sent to a different email', function () {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'someone-else@example.com',
        'invited_by' => $owner->id,
    ]);

    Socialite::fake('google');
    $this->get(route('auth.google.redirect', ['invitation' => $invitation->code]));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-7',
        'email' => 'not-invited@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('user.dashboard'));

    $user = User::query()->where('email', 'not-invited@example.com')->firstOrFail();

    expect($user->teams()->count())->toBe(0)
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

test('Google signup returns to the page a redirect field pointed at', function () {
    Socialite::fake('google');
    $this->get(route('auth.google.redirect', ['redirect' => '/properties/nice-house']));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-8',
        'name' => 'Redirected Google User',
        'email' => 'redirected-google@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect('/properties/nice-house');
});

test('an off-site Google redirect field is ignored', function () {
    Socialite::fake('google');
    $this->get(route('auth.google.redirect', ['redirect' => 'https://evil.example.com']));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-9',
        'name' => 'Safe Google User',
        'email' => 'safe-google@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('user.dashboard'));
});

test('Google authentication preserves invitation context after a failed callback', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect', ['invitation' => 'invite-code']));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-5',
        'email' => 'unverified@example.com',
        'email_verified' => false,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login', ['invitation' => 'invite-code']));
});
