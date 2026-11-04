<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** The API answers in JSON — errors too (422 field errors, 403, 404) — whatever the client's Accept header. */
final class ExpectsJson
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
