<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Mchekhashvili\TaxLegislation\Legislation\Models\TaxType */
final class TaxTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->country?->alpha2,
            'code' => $this->code,
            'name' => $this->name,
            'names' => $this->names,
            'level' => $this->level->value,
            'in_force_from' => $this->in_force_from?->format('Y-m-d'),
            'abolished_on' => $this->abolished_on?->format('Y-m-d'),
            'replaces_id' => $this->replaces_id,
            'introduced_by_id' => $this->introduced_by_id,
            'abolished_by_id' => $this->abolished_by_id,
            'engine' => $this->engine,
            'legal_basis' => $this->legal_basis,
            'notes' => $this->notes,
        ];
    }
}
