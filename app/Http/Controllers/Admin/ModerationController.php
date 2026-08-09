<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveConversationReportRequest;
use App\Models\ConversationReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModerationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $reports = ConversationReport::query()
            ->select(['id', 'conversation_id', 'reporter_id', 'reason', 'details', 'status', 'created_at'])
            ->with(['reporter:id,name', 'conversation:id,property_id,team_id,renter_id,status', 'conversation.property:id,name,slug', 'conversation.team:id,name', 'conversation.renter:id,name'])
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ConversationReport $report) => [
                'id' => $report->id,
                'reason' => $report->reason,
                'details' => $report->details,
                'status' => $report->status,
                'createdAt' => $report->created_at->diffForHumans(),
                'reporter' => $report->reporter->name,
                'conversationId' => $report->conversation_id,
                'conversationStatus' => $report->conversation->status->value,
                'propertyName' => $report->conversation->property->name,
                'teamName' => $report->conversation->team->name,
                'renterName' => $report->conversation->renter->name,
            ]);

        return Inertia::render('admin/moderation/Index', ['reports' => $reports]);
    }

    public function update(ResolveConversationReportRequest $request, ConversationReport $report): RedirectResponse
    {
        $report->update(['status' => $request->validated('status')]);
        $report->conversation()->update([
            'status' => ConversationStatus::from($request->validated('conversation_status')),
            'blocked_by' => null,
        ]);

        return back();
    }
}
