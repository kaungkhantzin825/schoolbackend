<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * This is an API-only backend — there is no `login` named route to send a
     * browser to. Returning null makes an unauthenticated request answer with
     * a clean 401 JSON response.
     *
     * The default (`route('login')`) threw RouteNotFoundException and surfaced
     * as a confusing 500 whenever a request arrived without an
     * `Accept: application/json` header (e.g. multipart file uploads).
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
