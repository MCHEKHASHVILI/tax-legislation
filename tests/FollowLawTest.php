<?php

declare(strict_types=1);

use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotComputed;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\Facades\Taxes;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\ConfigThresholdTable;
use Mchekhashvili\TaxLegislation\Rates\EloquentTableSource;
use Mchekhashvili\TaxLegislation\Rates\EloquentThresholdTable;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Sale;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\SalesTax;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Shop;

/*
| An invented Armenian sales tax and invented dates — they exercise the engine, they are not law.
*/

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    app(TaxRegistry::class)->register(SalesTax::class);
    app()->instance(RateTable::class, new ConfigRateTable(['1' => ['2000-01-01' => 18]]));

    $this->sale = fn (string $on, bool $food = false) => new Sale(new Shop('shop'), Money::AMD(1000), new DateTimeImmutable($on), $food);
    $this->type = fn (string $code, ?string $from, ?string $until = null, ?string $engine = null) => TaxType::query()->create([
        'country_id' => Country::code('AM')->id, 'code' => $code, 'names' => ['en' => ucfirst($code) . ' tax'], 'level' => TaxLevel::National,
        'in_force_from' => $from, 'abolished_on' => $until, 'engine' => $engine, 'legal_basis' => "Art. 1.$code",
    ]);
});

it('runs every tax when the country has no law recorded', function () {
    expect(Taxes::assess(($this->sale)('2010-01-01'))->for('sales'))->toBeInstanceOf(Taxable::class);
});

it('runs a tax only within its recorded period', function () {
    ($this->type)('sales', '2001-01-01', '2020-01-01', SalesTax::class);

    expect(Taxes::assess(($this->sale)('2010-06-01'))->for('sales'))->toBeInstanceOf(Taxable::class)
        ->and(Taxes::assess(($this->sale)('2000-06-01'))->for('sales'))->toBeNull()
        ->and(Taxes::assess(($this->sale)('2021-06-01'))->for('sales'))->toBeNull();
});

it('reports a tax in force that no engine computes', function () {
    ($this->type)('sales', null, null, SalesTax::class);
    ($this->type)('social', null, '2008-01-01');

    $before = Taxes::assess(($this->sale)('2007-06-01'));
    $after = Taxes::assess(($this->sale)('2008-06-01'));

    expect($before->for('social'))->toBeInstanceOf(NotComputed::class)
        ->and((string) $before->for('social'))->toBe('Not computed — Art. 1.social — Social tax: In force on this day, but no engine computes it.')
        ->and($before->for('sales'))->toBeInstanceOf(Taxable::class)
        ->and($after->for('social'))->toBeNull();
});

it('applies an exemption only while the recorded law had it', function () {
    $sales = ($this->type)('sales', null, null, SalesTax::class);
    Exemption::query()->create(['tax_type_id' => $sales->id, 'key' => '2', 'name' => 'Food', 'effective_until' => '2025-01-01']);

    expect(Taxes::assess(($this->sale)('2024-06-01', food: true))->for('sales'))->toBeInstanceOf(Exempt::class)
        ->and(Taxes::assess(($this->sale)('2025-06-01', food: true))->for('sales'))->toBeInstanceOf(Taxable::class);
});

it('can be told not to follow the recorded law', function () {
    ($this->type)('sales', '2001-01-01', '2020-01-01', SalesTax::class);
    config()->set('tax-legislation.follow_law', false);

    expect(Taxes::assess(($this->sale)('2021-06-01'))->for('sales'))->toBeInstanceOf(Taxable::class);
});

it('reads thresholds as they stood on the day, from config or the database', function () {
    $file = new ConfigThresholdTable(['sales' => ['registration' => ['5', ['' => 100_000, '2025-01-01' => 115_000]]]], 'AMD');

    expect((string) $file->threshold('sales', 'registration', new DateTimeImmutable('2024-06-01')))->toBe('100000.00 AMD')
        ->and((string) $file->threshold('sales', 'registration', new DateTimeImmutable('2025-06-01')))->toBe('115000.00 AMD');

    $sales = ($this->type)('sales', null);
    Threshold::query()->create(['tax_type_id' => $sales->id, 'key' => 'registration', 'amount' => Money::AMD(100_000)]);
    Threshold::amend($sales, 'registration', ['amount' => Money::AMD(115_000)], from: '2025-01-01');
    $db = new EloquentThresholdTable('AM');

    expect((string) $db->threshold('sales', 'registration', new DateTimeImmutable('2024-06-01')))->toBe('100000.00 AMD')
        ->and((string) $db->threshold('sales', 'registration', new DateTimeImmutable('2025-06-01')))->toBe('115000.00 AMD');
});

it('reads a table as it stood on the day', function () {
    $sales = ($this->type)('sales', null);
    TableEntry::query()->create(['tax_type_id' => $sales->id, 'table' => 'goods', 'key' => 'a', 'values' => ['rate' => 1]]);
    TableEntry::amend($sales, 'a', ['table' => 'goods', 'values' => ['rate' => 2]], from: '2025-01-01');
    $source = new EloquentTableSource('AM');

    expect($source->rows('sales', 'goods', new DateTimeImmutable('2024-06-01')))->toBe(['a' => ['rate' => 1]])
        ->and($source->rows('sales', 'goods', new DateTimeImmutable('2025-06-01')))->toBe(['a' => ['rate' => 2]]);
});
