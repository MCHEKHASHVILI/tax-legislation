<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Mchekhashvili\TaxLegislation\Actions\RecordLegalAct;
use Mchekhashvili\TaxLegislation\Http\Resources\LegalActResource;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;

/** @internal */
final class LegalActController extends Controller
{
    /** A country's acts, newest first. `?kind=law` narrows them. */
    public function index(Request $request, string $country): AnonymousResourceCollection
    {
        $acts = LegalAct::query()->ofCountry($this->country($country))->with('country')
            ->when($request->query('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->orderByRaw('adopted_on is null')->orderByDesc('adopted_on')->orderByDesc('id')
            ->paginate($this->perPage($request))->withQueryString();

        return LegalActResource::collection($acts);
    }

    /** The act, with what it did: taxes introduced and abolished, values set, the acts amending it. */
    public function show(LegalAct $legalAct): LegalActResource
    {
        return new LegalActResource($legalAct->load('country', 'amendments:id,amends_id')->loadCount(
            'taxTypesIntroduced', 'taxTypesAbolished', 'ratesSet', 'thresholdsSet', 'exemptionsSet', 'tableEntriesSet',
        ));
    }

    public function store(Request $request, RecordLegalAct $action): JsonResponse
    {
        return $this->submitted($action->handle($request->all()), LegalActResource::class);
    }
}
