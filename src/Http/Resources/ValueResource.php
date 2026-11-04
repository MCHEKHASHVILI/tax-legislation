<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Http\Subjects;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;

/**
 * A rate, threshold, exemption or table row, with its period and the acts that set and ended it.
 * Money is {amount: decimal string, currency}.
 *
 * @property TaxRate|Threshold|Exemption|TableEntry $resource
 */
final class ValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $value = $this->resource;

        return [
            'id' => $value->getKey(),
            'kind' => Subjects::alias($value::class),
            'tax_type_id' => $value->tax_type_id,
            'key' => $value->key,
            'article' => $value->article,
            'effective_from' => $value->effective_from?->format('Y-m-d'),
            'effective_until' => $value->effective_until?->format('Y-m-d'),
            'legal_act_id' => $value->legal_act_id,
            'repealed_by_id' => $value->repealed_by_id,
            ...match (true) {
                $value instanceof TaxRate => [
                    'name' => $value->name,
                    'percent' => $value->percent,
                    'amount' => self::money($value->amount),
                    'unit' => $value->unit,
                ],
                $value instanceof Threshold => [
                    'name' => $value->name,
                    'amount' => self::money($value->amount),
                    'period' => $value->period?->value,
                ],
                $value instanceof Exemption => [
                    'name' => $value->name,
                    'description' => $value->description,
                    'with_credit' => $value->with_credit,
                    'conditions' => $value->conditions,
                    'handler' => $value->handler,
                ],
                $value instanceof TableEntry => [
                    'table' => $value->table,
                    'values' => $value->values,
                ],
            },
        ];
    }

    /** @return ?array{amount: string, currency: string} */
    private static function money(?Money $money): ?array
    {
        return $money?->jsonSerialize();
    }
}
