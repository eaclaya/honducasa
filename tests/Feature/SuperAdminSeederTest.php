<?php

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

test('it provisions the configured superadmin with no password so Google sign-in can claim it', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', config('app.superadmin_email'))->sole();

    expect($user->is_admin)->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and($user->current_team_id)->not->toBeNull();
});

test('rerunning the seeder does not duplicate or reset the account', function () {
    $this->seed(SuperAdminSeeder::class);
    $firstRunId = User::query()->where('email', config('app.superadmin_email'))->sole()->id;

    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->where('email', config('app.superadmin_email'))->count())->toBe(1)
        ->and(User::query()->where('email', config('app.superadmin_email'))->sole()->id)->toBe($firstRunId);
});

test('an existing account at that email is promoted to admin without touching its password', function () {
    $user = User::factory()->create(['email' => config('app.superadmin_email'), 'is_admin' => false]);
    $originalPassword = $user->password;

    $this->seed(SuperAdminSeeder::class);

    expect($user->fresh()->is_admin)->toBeTrue()
        ->and($user->fresh()->password)->toBe($originalPassword);
});

test('it refuses to run without SUPERADMIN_EMAIL configured', function () {
    config()->set('app.superadmin_email', null);

    // Bypass Artisan (which $this->seed() runs through and would swallow the
    // exception into a console exit code) so the throw reaches Pest directly.
    app(SuperAdminSeeder::class)->run();
})->throws(RuntimeException::class, 'SUPERADMIN_EMAIL must be set before seeding the superadmin account.');
