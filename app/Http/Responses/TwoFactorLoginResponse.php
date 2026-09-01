<?php

namespace App\Http\Responses;

use App\Actions\Auth\ExecutePendingAuthAction;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function __construct(private ExecutePendingAuthAction $executePendingAuthAction) {}

    public function toResponse($request): Response
    {
        if ($redirect = $this->executePendingAuthAction->handle($request, $request->user())) {
            return redirect($redirect);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($this->requestedRedirect($request) ?? $this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
