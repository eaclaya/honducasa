<?php

use App\Actions\Admin\RecordAdminActivity;
use App\Actions\Listings\SetListingStatus;
use App\Enums\ListingStatus;
use App\Models\AdminActivity;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('the admin route group locks out guests and non-administrators', function () {
    $this->get(route('admin.moderation.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.moderation.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.moderation.index'))
        ->assertOk();
});

test('admin routes keep their names under the group prefix', function () {
    expect(route('admin.moderation.index', absolute: false))->toBe('/admin/moderation');
});

test('a listing without photos cannot be published, whoever asks', function () {
    $property = Property::factory()->create([
        'status' => ListingStatus::Draft,
        'published_at' => null,
    ]);

    $status = app(SetListingStatus::class)->handle($property, ListingStatus::Published);

    expect($status)->toBe(ListingStatus::Draft)
        ->and($property->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($property->fresh()->published_at)->toBeNull();
});

test('a listing with photos publishes and keeps its original publication date', function () {
    Storage::fake('public');
    $publishedAt = now()->subMonth()->startOfSecond();
    $property = Property::factory()->create([
        'status' => ListingStatus::Paused,
        'published_at' => $publishedAt,
    ]);
    $property->addMedia(UploadedFile::fake()->image('casa.jpg'))->toMediaCollection('photos');

    $status = app(SetListingStatus::class)->handle($property->fresh(), ListingStatus::Published);

    expect($status)->toBe(ListingStatus::Published)
        ->and($property->fresh()->published_at->equalTo($publishedAt))->toBeTrue();
});

test('moving a listing out of published clears its publication date', function () {
    $property = Property::factory()->create([
        'status' => ListingStatus::Published,
        'published_at' => now(),
    ]);

    app(SetListingStatus::class)->handle($property, ListingStatus::Archived);

    expect($property->fresh()->status)->toBe(ListingStatus::Archived)
        ->and($property->fresh()->published_at)->toBeNull();
});

test('an audit entry records who did what to which record', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $property = Property::factory()->create();

    $activity = app(RecordAdminActivity::class)->handle(
        $admin,
        'listing.archived',
        $property,
        'Anuncio duplicado',
        ['status' => ['from' => 'published', 'to' => 'archived']],
    );

    expect($activity->admin_id)->toBe($admin->id)
        ->and($activity->action)->toBe('listing.archived')
        ->and($activity->subject->is($property))->toBeTrue()
        ->and($activity->reason)->toBe('Anuncio duplicado')
        ->and($activity->changes)->toBe(['status' => ['from' => 'published', 'to' => 'archived']])
        ->and($activity->created_at)->not->toBeNull();
});

test('audit entries are never updated', function () {
    $activity = app(RecordAdminActivity::class)->handle(
        User::factory()->create(['is_admin' => true]),
        'impersonation.started',
    );

    expect(AdminActivity::UPDATED_AT)->toBeNull()
        ->and($activity->getAttributes())->not->toHaveKey('updated_at');
});

test('users, teams and messages can be suspended or redacted', function () {
    $user = User::factory()->withPersonalTeam()->create();

    expect($user->isSuspended())->toBeFalse()
        ->and($user->currentTeam->isSuspended())->toBeFalse();

    $user->update(['suspended_at' => now(), 'suspension_reason' => 'Spam']);
    $user->currentTeam->update(['suspended_at' => now()]);

    expect($user->fresh()->isSuspended())->toBeTrue()
        ->and($user->fresh()->suspension_reason)->toBe('Spam')
        ->and($user->currentTeam->fresh()->isSuspended())->toBeTrue();
});
