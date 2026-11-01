<?php

declare(strict_types=1);

use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Legislation\ActKind;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;
use Mchekhashvili\TaxLegislation\Legislation\ThresholdPeriod;
use Mchekhashvili\TaxLegislation\Rates\EloquentRateTable;

/*
| Acts, dates and figures here are invented to exercise the models; they are not legal history.
*/

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    $this->am = Country::code('AM');

    $this->code = LegalAct::query()->create([
        'country_id' => $this->am->id, 'kind' => ActKind::Code, 'number' => '1', 'title' => 'Example Tax Code',
        'adopted_on' => '2000-06-01', 'effective_on' => '2001-01-01',
    ]);
    $this->amendment = LegalAct::query()->create([
        'country_id' => $this->am->id, 'kind' => ActKind::Law, 'number' => '2', 'title' => 'Example amending law',
        'adopted_on' => '2023-10-01', 'effective_on' => '2024-01-01', 'amends_id' => $this->code->id,
    ]);
    $this->sales = TaxType::query()->create([
        'country_id' => $this->am->id, 'code' => 'sales', 'names' => ['en' => 'Sales tax', 'hy' => 'Վաճառքի հարկ'],
        'level' => TaxLevel::National, 'in_force_from' => '2001-01-01', 'introduced_by_id' => $this->code->id,
    ]);
});

it('ties acts to their country and to the acts they amend', function () {
    expect($this->amendment->amends->is($this->code))->toBeTrue()
        ->and($this->code->amendments->pluck('number')->all())->toBe(['2'])
        ->and(LegalAct::query()->ofCountry('AM')->count())->toBe(2)
        ->and(LegalAct::query()->ofCountry('GE')->count())->toBe(0)
        ->and($this->amendment->citation())->toBe('Example amending law №2 of 01.10.2023');
});

it('keeps tax types per country, in the app locale', function () {
    expect(TaxType::query()->ofCountry('AM')->current()->pluck('code')->all())->toBe(['sales'])
        ->and(TaxType::query()->ofCountry('GE')->count())->toBe(0)
        ->and($this->sales->country->alpha2)->toBe('AM')
        ->and($this->sales->introducedBy->is($this->code))->toBeTrue()
        ->and($this->sales->name)->toBe('Sales tax')
        ->and($this->sales->nameIn('hy'))->toBe('Վաճառքի հարկ');
});

it('abolishes a tax by an act, and links its successor', function () {
    $this->sales->abolish('2024-01-01', $this->amendment);
    $vat = TaxType::query()->create([
        'country_id' => $this->am->id, 'code' => 'vat', 'names' => ['en' => 'VAT'], 'level' => TaxLevel::National,
        'in_force_from' => '2024-01-01', 'replaces_id' => $this->sales->id, 'introduced_by_id' => $this->amendment->id,
    ]);

    expect(TaxType::query()->inForceOn('2023-12-31')->pluck('code')->all())->toBe(['sales'])
        ->and(TaxType::query()->inForceOn('2024-01-01')->pluck('code')->all())->toBe(['vat'])
        ->and($vat->predecessors()->pluck('code')->all())->toBe(['sales'])
        ->and($this->amendment->taxTypesAbolished->pluck('code')->all())->toBe(['sales'])
        ->and($this->amendment->taxTypesIntroduced->pluck('code')->all())->toBe(['vat']);
});

it('amends a rate without losing the old one', function () {
    $first = TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => '1', 'percent' => 20, 'article' => '1', 'legal_act_id' => $this->code->id]);
    $second = TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2024-01-01', by: $this->amendment);

    expect($first->fresh()->effective_until->format('Y-m-d'))->toBe('2024-01-01')
        ->and($second->article)->toBe('1')
        ->and($this->sales->rateOn('1', '2023-12-31')->percent)->toBe(20.0)
        ->and($this->sales->rateOn('1', '2024-01-01')->rate()->percent)->toBe(18.0)
        ->and(TaxRate::query()->historyOf($this->sales, '1')->pluck('percent')->all())->toBe([20.0, 18.0])
        ->and($this->amendment->ratesSet->pluck('percent')->all())->toBe([18.0]);
});

it('slots an amendment between two existing periods', function () {
    TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => '1', 'percent' => 20]);
    TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2024-01-01');
    $middle = TaxRate::amend($this->sales, '1', ['percent' => 19], from: '2020-01-01');

    expect($middle->effective_until->format('Y-m-d'))->toBe('2024-01-01')
        ->and($this->sales->rateOn('1', '2019-12-31')->percent)->toBe(20.0)
        ->and($this->sales->rateOn('1', '2022-01-01')->percent)->toBe(19.0)
        ->and($this->sales->rateOn('1', '2025-01-01')->percent)->toBe(18.0);
});

it('holds per-unit rates in money', function () {
    $rate = TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => '2', 'amount' => Money::AMD('4.5'), 'unit' => 'cm³']);

    expect((string) $rate->fresh()->amount)->toBe('4.50 AMD')->and($rate->rate())->toBeNull();
});

it('reads rates for the engine from the database', function () {
    TaxRate::query()->create(['tax_type_id' => $this->sales->id, 'key' => '1', 'percent' => 20]);
    TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2024-01-01');
    $table = new EloquentRateTable('AM');

    expect($table->rate('1', new DateTimeImmutable('2023-06-01'))->percent)->toBe(20.0)
        ->and($table->rate('1', new DateTimeImmutable('2024-06-01'))->percent)->toBe(18.0);

    new EloquentRateTable('GE')->rate('1', new DateTimeImmutable('2024-06-01'));
})->throws(OutOfBoundsException::class, 'GE: no rate for Art. 1');

it('keeps thresholds with the period they are measured over', function () {
    Threshold::query()->create(['tax_type_id' => $this->sales->id, 'key' => 'registration', 'amount' => Money::AMD(100_000), 'period' => ThresholdPeriod::Rolling12Months]);
    Threshold::amend($this->sales, 'registration', ['amount' => Money::AMD(115_000), 'period' => ThresholdPeriod::Rolling12Months], from: '2025-01-01', by: $this->amendment);

    $now = $this->sales->thresholdOn('registration', '2025-06-01');

    expect((string) $now->amount)->toBe('115000.00 AMD')
        ->and($now->period)->toBe(ThresholdPeriod::Rolling12Months)
        ->and((string) $this->sales->thresholdOn('registration', '2024-06-01')->amount)->toBe('100000.00 AMD');
});

it('repeals an exemption by an act', function () {
    $food = Exemption::query()->create(['tax_type_id' => $this->sales->id, 'key' => '2', 'name' => 'Food', 'conditions' => ['max_per_item' => 1000]]);
    $food->repeal('2024-01-01', $this->amendment);

    expect($food->repealedBy->is($this->amendment))->toBeTrue()
        ->and(Exemption::query()->ofTax($this->sales)->inForceOn('2023-06-01')->count())->toBe(1)
        ->and(Exemption::query()->ofTax($this->sales)->current()->count())->toBe(0)
        ->and($food->conditions)->toBe(['max_per_item' => 1000]);
});

it('keeps tables row by row, each with its own history', function () {
    TableEntry::query()->create(['tax_type_id' => $this->sales->id, 'table' => 'excise', 'key' => '2710 12', 'values' => ['unit' => 't', 'rate' => 500]]);
    TableEntry::query()->create(['tax_type_id' => $this->sales->id, 'table' => 'excise', 'key' => '2711', 'values' => ['unit' => 't', 'rate' => 300]]);
    TableEntry::query()->create(['tax_type_id' => $this->sales->id, 'table' => 'land', 'key' => '2710 12', 'values' => ['rate' => 1]]);
    TableEntry::amend($this->sales, '2710 12', ['table' => 'excise', 'values' => ['unit' => 't', 'rate' => 650]], from: '2026-01-01');

    $rates = fn (string $day) => $this->sales->tableOn('excise', $day)->mapWithKeys(fn ($e) => [$e->key => $e->values['rate']])->all();

    expect($rates('2025-06-01'))->toBe(['2710 12' => 500, '2711' => 300])
        ->and($rates('2026-06-01'))->toEqual(['2711' => 300, '2710 12' => 650])
        ->and($this->sales->tableOn('land', '2026-06-01')->sole()->values)->toBe(['rate' => 1]);
});

it('lists everything one act changed', function () {
    TaxRate::amend($this->sales, '1', ['percent' => 18], from: '2024-01-01', by: $this->amendment);
    Threshold::amend($this->sales, 'reg', ['amount' => Money::AMD(1)], from: '2024-01-01', by: $this->amendment);
    TableEntry::amend($this->sales, 'x', ['table' => 't', 'values' => []], from: '2024-01-01', by: $this->amendment);

    $act = $this->amendment->fresh()->loadCount(['ratesSet', 'thresholdsSet', 'exemptionsSet', 'tableEntriesSet']);

    expect([$act->rates_set_count, $act->thresholds_set_count, $act->exemptions_set_count, $act->table_entries_set_count])->toBe([1, 1, 0, 1]);
});
