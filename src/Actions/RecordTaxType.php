<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Closure;
use Illuminate\Validation\Rule;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Engine\Tax;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;

/**
 * Records a tax for a period — a new tax, a past one, or a tax brought back. Two periods of the
 * same tax in one country may not overlap — checked on submission and again on approval. A Draft
 * while approval is on.
 *
 *     app(RecordTaxType::class)->handle([
 *         'country' => 'GE', 'code' => 'social', 'names' => ['ka' => 'სოციალური გადასახადი', 'en' => 'Social tax'],
 *         'level' => 'national', 'in_force_from' => null, 'abolished_on' => '2008-01-01',
 *         'abolished_by_id' => $law->id, 'reason' => 'Past tax, from the 2004 Code',
 *     ]);
 */
final class RecordTaxType extends Action
{
    public function rules(array $input = []): array
    {
        $country = $this->countryId($input['country'] ?? null);
        $types = new TaxType()->getTable();

        return [
            'country' => ['required', 'string', 'size:2', Rule::exists(config('countries.table', 'countries'), 'alpha2')],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/'],
            'names' => ['required', 'array'],
            'names.en' => ['required', 'string', 'max:255'],
            'names.*' => ['string', 'max:255'],
            'level' => ['required', Rule::enum(TaxLevel::class)],
            'in_force_from' => ['nullable', 'date'],
            'abolished_on' => ['nullable', 'date', 'after:in_force_from'],
            'replaces_id' => ['nullable', 'integer', Rule::exists($types, 'id')->where('country_id', $country)],
            'introduced_by_id' => ['nullable', 'integer', $this->actOfCountry($country)],
            'abolished_by_id' => ['nullable', 'integer', $this->actOfCountry($country)],
            'engine' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (!is_subclass_of($value, Tax::class)) {
                    $fail('The :attribute must name a ' . Tax::class . ' class.');
                }
            }],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function handle(array $input): TaxType|Draft
    {
        return $this->submit($input);
    }

    protected function check(array $input): array
    {
        $valid = $this->validate($input);
        $country = $this->countryId($valid['country']);

        $overlap = TaxType::query()->where('country_id', $country)->where('code', $valid['code'])
            ->get()
            ->first(fn (TaxType $t) => self::overlaps($valid['in_force_from'] ?? null, $valid['abolished_on'] ?? null, $t));

        if ($overlap !== null) {
            $this->fail(['in_force_from' => "This period overlaps the recorded period of '{$valid['code']}' "
                . ($overlap->in_force_from?->format('Y-m-d') ?? 'from the start') . ' – ' . ($overlap->abolished_on?->format('Y-m-d') ?? 'now') . '.']);
        }

        return $valid;
    }

    protected function apply(array $valid): TaxType
    {
        return TaxType::query()->create([
            ...array_diff_key($valid, array_flip(['country', 'reason', 'source'])),
            'country_id' => $this->countryOf($valid),
        ]);
    }

    protected function countryOf(array $valid): ?int
    {
        return $this->countryId($valid['country']);
    }

    private static function overlaps(?string $from, ?string $until, TaxType $t): bool
    {
        $aFrom = $from ?? '0000-01-01';
        $aUntil = $until ?? '9999-12-31';
        $bFrom = $t->in_force_from?->format('Y-m-d') ?? '0000-01-01';
        $bUntil = $t->abolished_on?->format('Y-m-d') ?? '9999-12-31';

        return $aFrom < $bUntil && $bFrom < $aUntil;
    }
}
