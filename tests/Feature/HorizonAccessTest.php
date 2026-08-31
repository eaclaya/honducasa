<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('only administrators may view Horizon outside local environments', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
