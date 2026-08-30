<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PortalAwareLoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->user()?->isClient()) {
            return redirect()->intended(route('portal.projects.index'));
        }

        return redirect()->intended(config('fortify.home'));
    }
}
