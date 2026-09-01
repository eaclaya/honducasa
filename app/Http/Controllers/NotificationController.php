<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/Index', [
            'notifications' => $request->user()->notifications()->latest()->limit(100)->get()->map(fn ($notification) => [
                'id' => $notification->id,
                'conversationId' => $notification->data['conversation_id'] ?? null,
                'targetUrl' => $notification->data['target_url'] ?? null,
                'propertyName' => $notification->data['property_name'] ?? null,
                'senderLabel' => $notification->data['sender_label'] ?? null,
                'preview' => $notification->data['preview'] ?? null,
                'isRead' => $notification->read_at !== null,
                'createdAt' => $notification->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
