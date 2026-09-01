<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StorePendingAuthActionRequest;
use App\Support\SafeRedirectPath;
use Illuminate\Http\Response;

class PendingAuthActionController extends Controller
{
    public function __invoke(StorePendingAuthActionRequest $request): Response
    {
        $validated = $request->validated();

        $request->session()->put('auth.pending_action', [
            'type' => $validated['type'],
            'payload' => $validated['payload'],
            'redirect' => SafeRedirectPath::resolve($validated['redirect']),
        ]);

        return response()->noContent();
    }
}
