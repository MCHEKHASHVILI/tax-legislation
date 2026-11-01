<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/** Abolishes a tax from a day, citing the act. */
final class AbolishTaxType extends Action
{
    private ?TaxType $tax = null;

    public function rules(array $input = []): array
    {
        $from = $this->tax?->in_force_from?->format('Y-m-d');

        return [
            'on' => array_filter(['required', 'date', $from === null ? null : "after:$from"]),
            'legal_act_id' => ['required', 'integer', $this->actOfCountry($this->tax?->country_id)],
        ];
    }

    public function handle(TaxType $tax, array $input): TaxType
    {
        $this->tax = $tax;
        $valid = $this->validate($input);

        return $this->save($valid, fn () => $tax->abolish($valid['on'], \Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct::query()->find($valid['legal_act_id'])));
    }
}
