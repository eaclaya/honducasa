<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Http\Requests\UpdateConversationStatusRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

class ConversationStatusController extends Controller
{
    public function __invoke(UpdateConversationStatusRequest $request, Conversation $conversation): RedirectResponse
    {
        $status = ConversationStatus::from($request->validated('status'));

        if ($conversation->status === ConversationStatus::Blocked
            && $conversation->blocked_by !== $request->user()->id) {
            abort(403);
        }

        $conversation->update([
            'status' => $status,
            'blocked_by' => $status === ConversationStatus::Blocked ? $request->user()->id : null,
        ]);

        return to_route('messages.show', $conversation);
    }
}
