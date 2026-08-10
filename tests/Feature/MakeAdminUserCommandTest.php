<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates a new admin user when the email does not exist', function () {
    $this->artisan('app:make-admin-user', [
        'email' => 'new-admin@honducasa.hn',
        '--name' => 'New Admin',
        '--password' => 'a-very-secure-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'new-admin@honducasa.hn')->sole();

    expect($user->name)->toBe('New Admin')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('it grants admin access to an existing user instead of creating a duplicate', function () {
    $user = User::factory()->create(['email' => 'existing@honducasa.hn', 'is_admin' => false]);

    $this->artisan('app:make-admin-user', ['email' => 'existing@honducasa.hn'])
        ->assertSuccessful();

    expect(User::query()->where('email', 'existing@honducasa.hn')->count())->toBe(1)
        ->and($user->fresh()->is_admin)->toBeTrue();
});

test('it rejects an invalid email', function () {
    $this->artisan('app:make-admin-user', ['email' => 'not-an-email'])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
