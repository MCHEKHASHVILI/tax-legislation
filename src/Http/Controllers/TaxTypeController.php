<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Mchekhashvili\TaxLegislation\Actions\AbolishTaxType;
use Mchekhashvili\TaxLegislation\Actions\RecordTaxType;
use Mchekhashvili\TaxLegislation\Http\Resources\TaxTypeResource;
use Mchekhashvili\TaxLegislation\Http\Resources\ValueResource;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/** @internal */
final class TaxTypeController extends Controller
{
    /**
     * A country's taxes: those in force on `?on=YYYY-MM-DD`, or, without it, every recorded period
     * of every tax, by code.
     */
    public function index(Request $request, string $country): AnonymousResourceCollection
    {
        $query = TaxType::query()->ofCountry($this->country($country))->with('country');

        if ($request->has('on')) {
            $query->inForceOn($this->day($request));
        }

        return TaxTypeResource::collection($query->orderBy('code')->orderByRaw('in_force_from is not null')->orderBy('in_force_from')->get());
    }

    public function show(TaxType $taxType): TaxTypeResource
    {
        return new TaxTypeResource($taxType->load('country'));
    }

    /**
     * The tax's law on a day (`?on=`, today by default): its rates, thresholds, exemptions and table
     * rows in force then. Each value's history is at /tax-types/{id}/history/{kind}/{key}.
     */
    public function law(Request $request, TaxType $taxType): JsonResponse
    {
        $day = $this->day($request);
        $values = fn (string $relation) => ValueResource::collection(
            $taxType->{$relation}()->inForceOn($day)->orderBy('key')->get(),
        );

        return response()->json(['data' => [
            'tax_type' => new TaxTypeResource($taxType->load('country')),
            'on' => $day->format('Y-m-d'),
            'in_force' => $taxType->isInForceOn($day),
            'rates' => $values('rates'),
            'thresholds' => $values('thresholds'),
            'exemptions' => $values('exemptions'),
            'table_entries' => $values('tableEntries'),
        ]]);
    }

    public function store(Request $request, RecordTaxType $action): JsonResponse
    {
        return $this->submitted($action->handle($request->all()), TaxTypeResource::class);
    }

    public function abolish(Request $request, TaxType $taxType, AbolishTaxType $action): JsonResponse
    {
        return $this->submitted($action->handle($taxType, $request->all()), TaxTypeResource::class);
    }
}
