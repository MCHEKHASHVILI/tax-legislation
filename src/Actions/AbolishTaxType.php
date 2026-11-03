<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Illuminate\Database\Eloquent\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/** Abolishes a tax from a day, citing the act. A Draft while approval is on. */
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

    public function handle(TaxType $tax, array $input): TaxType|Draft
    {
        $this->tax = $tax;

        return $this->submit($input);
    }

    protected function apply(array $valid): TaxType
    {
        return $this->tax->abolish($valid['on'], LegalAct::query()->find($valid['legal_act_id']));
    }

    protected function countryOf(array $valid): ?int
    {
        return $this->tax?->country_id;
    }

    protected function subject(): ?Model
    {
        return $this->tax;
    }

    protected function restore(Draft $draft): void
    {
        $this->tax = $draft->subject instanceof TaxType ? $draft->subject : $this->fail(['draft' => 'The tax this draft abolishes no longer exists.']);
    }
}
