<?php

namespace App\Http\Responses;

use App\Actions\Auth\ExecutePendingAuthAction;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsToCurrentTeam;

    public function __construct(private ExecutePendingAuthAction $executePendingAuthAction) {}

    public function toResponse($request): Response
    {
        if ($redirect = $this->executePendingAuthAction->handle($request, $request->user())) {
            return redirect($redirect);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : redirect()->intended($this->requestedRedirect($request) ?? $this->redirectPathForCurrentTeam($request, Fortify::redirects('register')));
    }
}
