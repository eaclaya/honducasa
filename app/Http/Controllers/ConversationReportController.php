<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Http\Requests\StoreConversationReportRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ConversationReportController extends Controller
{
    public function store(StoreConversationReportRequest $request, Conversation $conversation): RedirectResponse
    {
        DB::transaction(function () use ($request, $conversation): void {
            $conversation->reports()->updateOrCreate(
                ['reporter_id' => $request->user()->id],
                [...$request->validated(), 'status' => 'pending'],
            );
            $conversation->update([
                'status' => ConversationStatus::Blocked,
                'blocked_by' => $request->user()->id,
            ]);
        });

        return to_route('messages.show', $conversation);
    }
}
