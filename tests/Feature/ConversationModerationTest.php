<?php

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationReport;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function moderationConversation(): array
{
    $property = Property::factory()->create();
    $renter = User::factory()->withPersonalTeam()->create();
    $conversation = Conversation::factory()->create([
        'property_id' => $property->id,
        'team_id' => $property->team_id,
        'renter_id' => $renter->id,
    ]);

    return [$conversation, $renter, $property->team->members()->firstOrFail()];
}

test('participants can close and reopen a conversation', function () {
    [$conversation, $renter] = moderationConversation();

    $this->actingAs($renter)
        ->patch(route('messages.status.update', $conversation), ['status' => 'closed'])
        ->assertRedirect(route('messages.show', $conversation));

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Closed);

    $this->actingAs($renter)
        ->post(route('messages.store', $conversation), ['body' => 'No debería enviarse mientras está cerrada.'])
        ->assertForbidden();

    $this->actingAs($renter)
        ->patch(route('messages.status.update', $conversation), ['status' => 'active'])
        ->assertRedirect(route('messages.show', $conversation));

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Active);
});

test('only the participant who blocked a conversation can unblock it', function () {
    [$conversation, $renter, $teamMember] = moderationConversation();

    $this->actingAs($renter)
        ->patch(route('messages.status.update', $conversation), ['status' => 'blocked'])
        ->assertRedirect();

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Blocked)
        ->and($conversation->blocked_by)->toBe($renter->id);

    $this->actingAs($teamMember)
        ->patch(route('messages.status.update', $conversation), ['status' => 'active'])
        ->assertForbidden();

    $this->actingAs($renter)
        ->patch(route('messages.status.update', $conversation), ['status' => 'active'])
        ->assertRedirect();
});

test('reporting creates a pending moderation record and blocks the conversation', function () {
    [$conversation, $renter] = moderationConversation();

    $this->actingAs($renter)
        ->post(route('messages.reports.store', $conversation), [
            'reason' => 'contact_sharing',
            'details' => 'La otra persona intenta sacar la conversación de la plataforma.',
        ])
        ->assertRedirect(route('messages.show', $conversation));

    $report = ConversationReport::query()->sole();
    expect($report->reporter_id)->toBe($renter->id)
        ->and($report->status)->toBe('pending')
        ->and($conversation->refresh()->status)->toBe(ConversationStatus::Blocked);
});

test('the shared navigation exposes the unread message count', function () {
    [$conversation, $renter, $teamMember] = moderationConversation();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $renter->id,
        'read_at' => null,
    ]);

    $this->actingAs($teamMember)
        ->get(route('dashboard', $teamMember->currentTeam))
        ->assertInertia(fn (Assert $page) => $page->where('unreadMessages', 1));
});
