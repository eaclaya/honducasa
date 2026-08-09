<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a renter starts one property conversation and sends messages inside the app', function () {
    $property = Property::factory()->create();
    $renter = User::factory()->create();

    $this->actingAs($renter)
        ->post(route('conversations.store', $property), ['body' => 'Me interesa conocer las condiciones y disponibilidad de esta propiedad.'])
        ->assertRedirect();

    $conversation = Conversation::query()->sole();
    expect($conversation->property_id)->toBe($property->id)
        ->and($conversation->team_id)->toBe($property->team_id)
        ->and($conversation->renter_id)->toBe($renter->id)
        ->and($conversation->messages()->sole()->body)->toContain('disponibilidad');

    $this->actingAs($renter)
        ->post(route('conversations.store', $property), ['body' => 'También quisiera saber cuándo se puede visitar la propiedad.'])
        ->assertRedirect(route('messages.show', $conversation));

    expect(Conversation::query()->count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(2);
});

test('contact details and external links are rejected from chat messages', function (string $body) {
    $property = Property::factory()->create();
    $renter = User::factory()->create();

    $this->actingAs($renter)
        ->from(route('properties.show', $property))
        ->post(route('conversations.store', $property), ['body' => $body])
        ->assertRedirect(route('properties.show', $property))
        ->assertSessionHasErrors('body');

    expect(Message::query()->count())->toBe(0);
})->with([
    'email' => 'Por favor escríbeme directamente a renter@example.com para coordinar.',
    'phone' => 'Puedes llamarme directamente al +504 9999-1234 para coordinar.',
    'link' => 'Mira mi información en https://example.com/contacto para coordinar.',
]);

test('the renter and property team can view and reply but outsiders cannot', function () {
    $property = Property::factory()->create();
    $renter = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'property_id' => $property->id,
        'team_id' => $property->team_id,
        'renter_id' => $renter->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $renter->id,
    ]);
    $teamMember = $property->team->members()->firstOrFail();

    $this->actingAs($renter)->get(route('messages.show', $conversation))->assertOk();
    $this->actingAs($teamMember)
        ->post(route('messages.store', $conversation), ['body' => 'La propiedad está disponible. ¿Qué deseas saber sobre sus condiciones?'])
        ->assertRedirect(route('messages.show', $conversation));
    $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
    $this->actingAs($outsider)
        ->post(route('messages.store', $conversation), ['body' => 'Este mensaje no debería permitirse.'])
        ->assertForbidden();
});

test('the inbox returns property context without exposing private contact details', function () {
    $property = Property::factory()->create(['name' => 'Casa Segura']);
    $renter = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'property_id' => $property->id,
        'team_id' => $property->team_id,
        'renter_id' => $renter->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $renter->id,
        'body' => 'Quisiera conocer las reglas y disponibilidad de esta casa.',
    ]);

    $this->actingAs($renter)
        ->get(route('messages.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->has('conversations', 1)
            ->where('selected.propertyName', 'Casa Segura')
            ->where('selected.messages.0.isMine', true)
            ->missing('selected.email')
            ->missing('selected.phone'));
});
