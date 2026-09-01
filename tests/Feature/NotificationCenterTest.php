<?php

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ConversationMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('new messages create privacy-safe database notifications for the other participant', function () {
    $property = Property::factory()->create(['name' => 'Casa Privada']);
    $renter = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'property_id' => $property->id,
        'team_id' => $property->team_id,
        'renter_id' => $renter->id,
    ]);
    $teamMember = $property->team->members()->firstOrFail();
    Notification::fake();

    $this->actingAs($renter)->post(route('messages.store', $conversation), [
        'body' => 'Quisiera confirmar si la propiedad continúa disponible esta semana.',
    ])->assertRedirect();

    Notification::assertSentTo($teamMember, ConversationMessageReceived::class, function ($notification, $channels) use ($conversation) {
        return $notification->conversationId === $conversation->id
            && $notification->senderLabel === 'Persona interesada'
            && ! str_contains($notification->preview, '@')
            && in_array('mail', $channels, true);
    });
});

test('a new message notification emails the recipient with a link to the conversation', function () {
    $user = User::factory()->create();
    $notification = new ConversationMessageReceived(10, 'Casa Centro', 'Equipo Centro', 'Hay una nueva respuesta.');

    expect($notification->via($user))->toContain('mail');

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Nuevo mensaje sobre Casa Centro')
        ->and($mail->actionUrl)->toBe(route('messages.show', 10))
        ->and(implode(' ', $mail->introLines))->toContain('Hay una nueva respuesta.');
});

test('users can view and mark their own notifications as read', function () {
    $user = User::factory()->create();
    $notification = new ConversationMessageReceived(10, 'Casa Centro', 'Equipo Centro', 'Hay una nueva respuesta.');
    $user->notifyNow($notification);
    $stored = $user->notifications()->sole();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications', 1)
            ->where('notifications.0.propertyName', 'Casa Centro'));

    $this->actingAs($user)
        ->patch(route('notifications.read', $stored->id))
        ->assertRedirect();

    expect($stored->refresh()->read_at)->not->toBeNull();
});

test('users cannot mark another users notification as read', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $owner->notifyNow(new ConversationMessageReceived(10, 'Casa Centro', 'Equipo Centro', 'Nueva respuesta.'));
    $stored = $owner->notifications()->sole();

    $this->actingAs($outsider)->patch(route('notifications.read', $stored->id))->assertNotFound();
});
