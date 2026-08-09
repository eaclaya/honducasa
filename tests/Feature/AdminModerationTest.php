<?php

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('only platform administrators can open moderation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.moderation.index'))->assertOk();
    $this->actingAs($user)->get(route('admin.moderation.index'))->assertForbidden();
});

test('administrators can resolve a report and control conversation status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $property = Property::factory()->create();
    $conversation = Conversation::factory()->create([
        'property_id' => $property->id,
        'team_id' => $property->team_id,
        'status' => ConversationStatus::Blocked,
    ]);
    $report = ConversationReport::factory()->create([
        'conversation_id' => $conversation->id,
        'reason' => 'fraud',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderation.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation/Index')
            ->where('reports.data.0.reason', 'fraud'));

    $this->actingAs($admin)->patch(route('admin.moderation.update', $report), [
        'status' => 'dismissed',
        'conversation_status' => 'active',
    ])->assertRedirect();

    expect($report->refresh()->status)->toBe('dismissed')
        ->and($conversation->refresh()->status)->toBe(ConversationStatus::Active);
});

test('non administrators cannot resolve moderation reports', function () {
    $user = User::factory()->create();
    $report = ConversationReport::factory()->create();

    $this->actingAs($user)->patch(route('admin.moderation.update', $report), [
        'status' => 'actioned',
        'conversation_status' => 'blocked',
    ])->assertForbidden();
});
