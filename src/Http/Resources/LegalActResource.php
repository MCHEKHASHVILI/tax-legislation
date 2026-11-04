<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct */
final class LegalActResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->country?->alpha2,
            'kind' => $this->kind->value,
            'number' => $this->number,
            'title' => $this->title,
            'citation' => $this->citation(),
            'issuer' => $this->issuer,
            'adopted_on' => $this->adopted_on?->format('Y-m-d'),
            'published_on' => $this->published_on?->format('Y-m-d'),
            'effective_on' => $this->effective_on?->format('Y-m-d'),
            'url' => $this->url,
            'amends_id' => $this->amends_id,
            'notes' => $this->notes,
            // What the act did, on GET /legal-acts/{id}.
            'effects' => $this->when(array_key_exists('rates_set_count', $this->resource->getAttributes()), fn () => [
                'tax_types_introduced' => (int) $this->tax_types_introduced_count,
                'tax_types_abolished' => (int) $this->tax_types_abolished_count,
                'rates' => (int) $this->rates_set_count,
                'thresholds' => (int) $this->thresholds_set_count,
                'exemptions' => (int) $this->exemptions_set_count,
                'table_entries' => (int) $this->table_entries_set_count,
                'amended_by' => $this->amendments->modelKeys(),
            ]),
        ];
    }
}
