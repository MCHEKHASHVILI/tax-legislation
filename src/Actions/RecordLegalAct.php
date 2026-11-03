<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Legislation\ActKind;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;

/**
 * Records a legal act: a code, an amending law, a resolution, a municipal act. A Draft while
 * approval is on.
 *
 *     app(RecordLegalAct::class)->handle([
 *         'country' => 'GE', 'kind' => 'law', 'number' => '1234', 'title' => 'Law of Georgia',
 *         'adopted_on' => '2026-11-20', 'effective_on' => '2027-01-01', 'amends_id' => $code->id,
 *         'url' => 'https://matsne.gov.ge/…', 'reason' => 'Entered from the Legislative Herald',
 *     ]);
 */
final class RecordLegalAct extends Action
{
    public function rules(array $input = []): array
    {
        return [
            'country' => ['required', 'string', 'size:2', Rule::exists(config('countries.table', 'countries'), 'alpha2')],
            'kind' => ['required', Rule::enum(ActKind::class)],
            'number' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:500'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'adopted_on' => ['nullable', 'date'],
            'published_on' => ['nullable', 'date', 'after_or_equal:adopted_on'],
            'effective_on' => ['nullable', 'date', 'after_or_equal:adopted_on'],
            'url' => ['nullable', 'url', 'max:2000'],
            'amends_id' => ['nullable', 'integer', Rule::exists(new LegalAct()->getTable(), 'id'), $this->actOfCountry($this->countryId($input['country'] ?? null))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function handle(array $input): LegalAct|Draft
    {
        return $this->submit($input);
    }

    protected function apply(array $valid): LegalAct
    {
        return LegalAct::query()->create([
            ...array_diff_key($valid, array_flip(['country', 'reason', 'source'])),
            'country_id' => $this->countryOf($valid),
        ]);
    }

    protected function countryOf(array $valid): ?int
    {
        return $this->countryId($valid['country']);
    }
}
