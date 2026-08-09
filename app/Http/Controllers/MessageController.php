<?php

namespace App\Http\Controllers;

use App\Actions\NotifyConversationParticipants;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, NotifyConversationParticipants $notify): RedirectResponse
    {
        DB::transaction(function () use ($request, $conversation, $notify): void {
            $message = $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $request->validated('body')]);
            $conversation->update(['last_message_at' => now()]);
            $notify->handle($conversation, $message);
        });

        return to_route('messages.show', $conversation);
    }
}
