<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Http\Resources\DraftResource;

/** @internal */
abstract class Controller
{
    /** Answers a form: 202 with the draft while approval is on, else 201 with the record. */
    protected function submitted(Model $result, string $resource): JsonResponse
    {
        return $result instanceof Draft
            ? new DraftResource($result)->response()->setStatusCode(202)
            : new $resource($result)->response()->setStatusCode(201);
    }

    /** The day the client asks about (`?on=YYYY-MM-DD`), today by default. */
    protected function day(Request $request): CarbonImmutable
    {
        $request->validate(['on' => ['nullable', 'date']]);

        return CarbonImmutable::parse($request->query('on') ?? 'today')->startOfDay();
    }

    /** A country by any ISO 3166-1 code: GE, GEO, 268. 404 if none. */
    protected function country(string $code): Country
    {
        return Country::code($code);
    }

    protected function perPage(Request $request): int
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);

        return (int) $request->query('per_page', 25);
    }

}
