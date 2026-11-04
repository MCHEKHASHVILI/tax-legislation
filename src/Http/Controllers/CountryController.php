<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Http\Resources\CountryResource;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/** @internal */
final class CountryController extends Controller
{
    /** The countries with law recorded; `?all=1` for every ISO 3166-1 country. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $countries = $this->withCounts(Country::query())
            ->when(!$request->boolean('all'), fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereIn('id', LegalAct::query()->select('country_id'))
                ->orWhereIn('id', TaxType::query()->select('country_id'))))
            ->orderBy('alpha2')
            ->get();

        return CountryResource::collection($countries);
    }

    public function show(string $country): CountryResource
    {
        return new CountryResource($this->withCounts(Country::query())->findOrFail($this->country($country)->getKey()));
    }

    /** @param Builder<Country> $query @return Builder<Country> */
    private function withCounts(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->select("$table.*")
            ->selectSub(LegalAct::query()->selectRaw('count(*)')->whereColumn('country_id', "$table.id"), 'legal_acts_count')
            ->selectSub(TaxType::query()->selectRaw('count(*)')->whereColumn('country_id', "$table.id"), 'tax_types_count');
    }
}
