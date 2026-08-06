<?php

use App\Enums\IdentityProvider;
use App\Models\OauthIdentity;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('users can start Google authentication', function () {
    Socialite::fake('google');

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();
});

test('a verified Google identity creates and authenticates a user', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-subject-1',
        'name' => 'Google User',
        'email' => 'google@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $user = User::query()->where('email', 'google@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));

    expect($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->personalTeam())->not->toBeNull();

    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => IdentityProvider::Google->value,
        'provider_subject' => 'google-subject-1',
    ]);
});

test('a returning Google identity authenticates the linked user without duplication', function () {
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
