<?php

use App\Models\Conversation;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Laravel\mock;

test('a favorite attempted as a guest is executed after manual login', function () {
    $user = User::factory()->create(['email' => 'renter@example.com']);
    $property = Property::factory()->create();
    $returnUrl = "/properties/{$property->slug}?from=rentals";

    $this->post(route('auth.pending-action.store'), [
        'type' => 'favorite_property',
        'payload' => ['property_slug' => $property->slug],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect($returnUrl);

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('property_favorites', [
        'user_id' => $user->id,
        'property_id' => $property->id,
    ]);
});

test('a saved search attempted as a guest is executed after registration', function () {
    Notification::fake();
    $returnUrl = '/rentals?location=Tegucigalpa&bedrooms=2';

    $this->post(route('auth.pending-action.store'), [
        'type' => 'save_search',
        'payload' => [
            'saved_search' => [
                'name' => 'Propiedades en Tegucigalpa',
                'filters' => [
                    'location' => 'Tegucigalpa',
                    'bedrooms' => 2,
                ],
                'alerts_enabled' => true,
            ],
        ],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    $this->post(route('register.store'), [
        'name' => 'New Renter',
        'email' => 'new-renter@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect($returnUrl);

    $user = User::query()->where('email', 'new-renter@example.com')->firstOrFail();
    $search = SavedSearch::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($search->user_id)->toBe($user->id)
        ->and($search->name)->toBe('Propiedades en Tegucigalpa')
        ->and($search->filters)->toMatchArray([
            'location' => 'Tegucigalpa',
            'bedrooms' => 2,
        ]);
});

test('a listing message drafted as a guest is sent after manual login', function () {
    $user = User::factory()->create(['email' => 'message-renter@example.com']);
    $property = Property::factory()->create();
    $returnUrl = "/properties/{$property->slug}";
    $body = 'Me interesa esta propiedad y quisiera confirmar si todavía está disponible.';

    $this->post(route('auth.pending-action.store'), [
        'type' => 'start_conversation',
        'payload' => [
            'property_slug' => $property->slug,
            'body' => $body,
        ],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect($returnUrl);

    $conversation = Conversation::query()->sole();

    expect($conversation->renter_id)->toBe($user->id)
        ->and($conversation->property_id)->toBe($property->id)
        ->and($conversation->messages()->sole()->body)->toBe($body);
});

test('a listing message drafted as a guest is sent after registration', function () {
    Notification::fake();
    $property = Property::factory()->create();
    $returnUrl = "/properties/{$property->slug}";
    $body = 'Quisiera conocer las condiciones para poder alquilar esta propiedad.';

    $this->post(route('auth.pending-action.store'), [
        'type' => 'start_conversation',
        'payload' => [
            'property_slug' => $property->slug,
            'body' => $body,
        ],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    $this->post(route('register.store'), [
        'name' => 'New Message Renter',
        'email' => 'new-message-renter@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect($returnUrl);

    $user = User::query()->where('email', 'new-message-renter@example.com')->firstOrFail();
    $conversation = Conversation::query()->sole();

    expect($conversation->renter_id)->toBe($user->id)
        ->and($conversation->messages()->sole()->body)->toBe($body);
});

test('a pending guest action survives a failed login attempt', function () {
    $user = User::factory()->create(['email' => 'retry@example.com']);
    $property = Property::factory()->create();

    $this->post(route('auth.pending-action.store'), [
        'type' => 'favorite_property',
        'payload' => ['property_slug' => $property->slug],
        'redirect' => "/properties/{$property->slug}",
    ])->assertNoContent();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect("/properties/{$property->slug}");

    $this->assertDatabaseHas('property_favorites', [
        'user_id' => $user->id,
        'property_id' => $property->id,
    ]);
});

test('a pending guest action is executed after Google authentication', function () {
    $property = Property::factory()->create();
    $returnUrl = "/properties/{$property->slug}";

    $this->post(route('auth.pending-action.store'), [
        'type' => 'favorite_property',
        'payload' => ['property_slug' => $property->slug],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'pending-action-google-subject',
        'name' => 'Google Renter',
        'email' => 'google-renter@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect($returnUrl);

    $user = User::query()->where('email', 'google-renter@example.com')->firstOrFail();

    $this->assertDatabaseHas('property_favorites', [
        'user_id' => $user->id,
        'property_id' => $property->id,
    ]);
});

test('a pending guest action is executed after Google One Tap authentication', function () {
    $property = Property::factory()->create();
    $returnUrl = "/properties/{$property->slug}";

    $this->post(route('auth.pending-action.store'), [
        'type' => 'favorite_property',
        'payload' => ['property_slug' => $property->slug],
        'redirect' => $returnUrl,
    ])->assertNoContent();

    mock(GoogleIdTokenVerifier::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn([
            'sub' => 'pending-action-one-tap-subject',
            'name' => 'One Tap Renter',
            'email' => 'one-tap-renter@example.com',
            'email_verified' => true,
        ]);

    $this->post(route('auth.google.one-tap'), [
        'credential' => 'google-id-token',
        'redirect' => '/dashboard',
    ])->assertRedirect($returnUrl);

    $user = User::query()->where('email', 'one-tap-renter@example.com')->firstOrFail();

    $this->assertDatabaseHas('property_favorites', [
        'user_id' => $user->id,
        'property_id' => $property->id,
    ]);
});

test('pending guest actions reject off-site return URLs', function () {
    $this->post(route('auth.pending-action.store'), [
        'type' => 'favorite_property',
        'payload' => ['property_slug' => 'some-property'],
        'redirect' => 'https://evil.example.com',
    ])->assertSessionHasErrors('redirect');
});
