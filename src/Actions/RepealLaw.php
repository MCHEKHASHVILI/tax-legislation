<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Illuminate\Database\Eloquent\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/**
 * A law ends a rate, threshold, exemption or table row from a day. A Draft while approval is on.
 *
 *     app(RepealLaw::class)->handle($exemption, ['on' => '2027-01-01', 'legal_act_id' => $law->id]);
 */
final class RepealLaw extends Action
{
    /** @var (\Illuminate\Database\Eloquent\Model&object{effective_from: ?\Carbon\CarbonImmutable, tax_type_id: int})|null */
    private ?object $value = null;

    public function rules(array $input = []): array
    {
        $from = $this->value?->effective_from?->format('Y-m-d');
        $country = $this->value === null ? null : TaxType::query()->whereKey($this->value->tax_type_id)->value('country_id');

        return [
            'on' => array_filter(['required', 'date', $from === null ? null : "after:$from"]),
            'legal_act_id' => ['required', 'integer', $this->actOfCountry($country)],
        ];
    }

    public function handle(object $value, array $input): object
    {
        $this->value = $value;

        return $this->submit($input);
    }

    protected function apply(array $valid): Model
    {
        return $this->value->repeal($valid['on'], LegalAct::query()->find($valid['legal_act_id']));
    }

    protected function countryOf(array $valid): ?int
    {
        return TaxType::query()->whereKey($this->value?->tax_type_id)->value('country_id');
    }

    protected function subject(): ?Model
    {
        return $this->value;
    }

    protected function restore(Draft $draft): void
    {
        $this->value = $draft->subject ?? $this->fail(['draft' => 'The value this draft repeals no longer exists.']);
    }
}
