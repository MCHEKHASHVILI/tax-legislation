<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Mchekhashvili\Countries\Models\Country */
final class CountryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'alpha2' => $this->alpha2,
            'alpha3' => $this->alpha3,
            'numeric' => $this->numeric,
            'name' => $this->name,
            'names' => $this->names,
            'currency' => $this->getAttribute('currency'),
            'legal_acts_count' => $this->whenCounted('legal_acts_count', fn () => (int) $this->legal_acts_count),
            'tax_types_count' => $this->whenCounted('tax_types_count', fn () => (int) $this->tax_types_count),
        ];
    }
}
