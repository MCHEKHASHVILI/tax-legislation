<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\Money\Currency;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\ThresholdPeriod;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;

/**
 * A law changes a rate, a threshold, an exemption or a table row from a day. The value in force
 * then is closed and the new one opened; nothing is overwritten. Every amendment cites its act.
 *
 *     app(AmendLaw::class)->handle(ValueKind::Rate, [
 *         'tax_type_id' => $vat->id, 'key' => '166', 'article' => '166',
 *         'from' => '2027-01-01', 'legal_act_id' => $law->id, 'percent' => 20,
 *         'reason' => 'Law №1234, Art. 1.5',
 *     ]);
 *
 * Money comes in as a decimal and a currency: 'amount' => '100000', 'currency' => 'GEL'.
 *
 * While approval is on, handle() returns a Draft; the row in force is closed and the new one opened
 * only when it is approved.
 */
final class AmendLaw extends Action
{
    private ?ValueKind $kind = null;

    public function rules(array $input = []): array
    {
        $tax = isset($input['tax_type_id']) ? TaxType::query()->find($input['tax_type_id']) : null;

        $common = [
            'tax_type_id' => ['required', 'integer', Rule::exists(new TaxType()->getTable(), 'id')],
            'key' => ['required', 'string', 'max:191'],
            'article' => ['nullable', 'string', 'max:191'],
            'from' => array_filter(['required', 'date', $this->withinTaxPeriod($tax)]),
            'legal_act_id' => ['required', 'integer', $this->actOfCountry($tax?->country_id)],
            'name' => ['nullable', 'string', 'max:255'],
        ];

        return $common + match ($this->kind ?? ValueKind::tryFrom((string) ($input['kind'] ?? ''))) {
            ValueKind::Rate => [
                'percent' => ['nullable', 'numeric', 'between:0,100', 'required_without:amount'],
                'amount' => ['nullable', 'numeric', 'required_without:percent'],
                'currency' => ['nullable', 'required_with:amount', $this->currency()],
                'unit' => ['nullable', 'string', 'max:40'],
            ],
            ValueKind::Threshold => [
                'amount' => ['required', 'numeric', 'min:0'],
                'currency' => ['required', $this->currency()],
                'period' => ['nullable', Rule::enum(ThresholdPeriod::class)],
            ],
            ValueKind::Exemption => [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'with_credit' => ['boolean'],
                'conditions' => ['nullable', 'array'],
                'handler' => ['nullable', 'string', 'max:255'],
            ],
            ValueKind::TableEntry => [
                'table' => ['required', 'string', 'max:60'],
                'values' => ['required', 'array'],
            ],
            null => ['kind' => ['required', Rule::enum(ValueKind::class)]],
        };
    }

    public function handle(ValueKind $kind, array $input): object
    {
        $this->kind = $kind;

        return $this->submit($input);
    }

    protected function apply(array $valid): Model
    {
        $tax = TaxType::query()->findOrFail($valid['tax_type_id']);
        $act = LegalAct::query()->findOrFail($valid['legal_act_id']);

        $values = array_diff_key($valid, array_flip(['tax_type_id', 'key', 'article', 'from', 'legal_act_id', 'reason', 'source', 'currency', 'kind']));

        if (isset($values['amount'])) {
            $values['amount'] = Money::of((string) $values['amount'], $valid['currency']);
        }

        $model = $this->kind->model();

        return $model::amend($tax, $valid['key'], $values, from: $valid['from'], by: $act, article: $valid['article'] ?? null);
    }

    protected function countryOf(array $valid): ?int
    {
        return TaxType::query()->whereKey($valid['tax_type_id'])->value('country_id');
    }

    protected function kind(): ?ValueKind
    {
        return $this->kind;
    }

    protected function restore(Draft $draft): void
    {
        $this->kind = $draft->kind;
    }

    /** A rule: the day must fall within the tax's own period. */
    private function withinTaxPeriod(?TaxType $tax): ?Closure
    {
        return $tax === null ? null : function (string $attribute, mixed $value, Closure $fail) use ($tax) {
            $day = new \DateTimeImmutable((string) $value);

            if (!$tax->isInForceOn($day)) {
                $fail("The tax '{$tax->code}' was not in force on {$day->format('Y-m-d')}.");
            }
        };
    }

    private function currency(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (!is_string($value) || !Currency::isKnown($value)) {
                $fail('The :attribute must be an ISO 4217 currency code.');
            }
        };
    }
}
