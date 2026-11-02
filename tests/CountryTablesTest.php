<?php

declare(strict_types=1);

use Mchekhashvili\Countries\Database\Seeders\CountrySeeder;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\Facades\Taxes;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\ConfigThresholdTable;
use Mchekhashvili\TaxLegislation\Rates\CountryTables;
use Mchekhashvili\TaxLegislation\Rates\EloquentRateTable;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\TableSource;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Sale;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\SalesTax;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Shop;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\TurnoverTax;

/*
| Two invented countries' taxes in one app, both keyed by article '1' — they exercise the
| per-country tables, they are not law.
*/

beforeEach(function () {
    $this->seed(CountrySeeder::class);
    app(TaxRegistry::class)->register(SalesTax::class, TurnoverTax::class);

    $this->tables = app(CountryTables::class)
        ->register('AM', rates: new ConfigRateTable(['1' => ['2000-01-01' => 18]]))
        ->register('AZ',
            rates: fn () => new ConfigRateTable(['1' => ['2000-01-01' => 5]]),
            thresholds: fn () => new ConfigThresholdTable(['turnover' => ['small' => ['2', ['' => 100]]]], 'AZN'),
        );

    $this->sale = fn (int $amount, string $on = '2026-10-30') => new Sale(new Shop('shop'), Money::AMD($amount), new DateTimeImmutable($on));
});

it('gives each country its own rates in one run', function () {
    $report = Taxes::assess(($this->sale)(1000));

    expect((string) $report->for('sales')->amount)->toBe('180.00 AMD')     // a handler type-hinting RateTable
        ->and((string) $report->for('turnover')->amount)->toBe('50.00 AMD'); // a tax calling rateTable()
});

it('gives each country its own thresholds', function () {
    expect(Taxes::assess(($this->sale)(99))->for('turnover'))->toBeInstanceOf(NotTaxable::class)
        ->and(Taxes::forCountry('AM')->assess(($this->sale)(99))->for('sales')->rate->percent)->toBe(18.0);
});

it('reads an unregistered source from the recorded law', function () {
    $type = TaxType::query()->create([
        'country_id' => Country::code('KZ')->id, 'code' => 'turnover', 'names' => ['en' => 'Turnover tax'],
        'level' => TaxLevel::National, 'in_force_from' => '2001-01-01',
    ]);
    TaxRate::query()->create(['tax_type_id' => $type->id, 'key' => '1', 'percent' => 2]);
    TaxRate::amend($type, '1', ['percent' => 4], from: '2025-01-01');
    Threshold::query()->create(['tax_type_id' => $type->id, 'key' => 'small', 'amount' => Money::KZT(100)]);

    expect($this->tables->rates('kz'))->toBeInstanceOf(EloquentRateTable::class)   // nothing registered for KZ
        ->and($this->tables->rates('KZ')->rate('1', new DateTimeImmutable('2024-06-01'))->percent)->toBe(2.0)
        ->and($this->tables->rates('KZ')->rate('1', new DateTimeImmutable('2025-06-01'))->percent)->toBe(4.0)
        ->and((string) $this->tables->thresholds('KZ')->threshold('turnover', 'small', new DateTimeImmutable('2025-06-01')))->toBe('100.00 KZT')
        ->and($this->tables->rates('AZ'))->toBeInstanceOf(ConfigRateTable::class);
});

it('builds each source once per request', function () {
    $built = 0;
    $this->tables->register('AZ', rates: function () use (&$built) {
        $built++;

        return new ConfigRateTable(['1' => ['2000-01-01' => 5]]);
    });

    $this->tables->rates('AZ');
    $this->tables->rates('AZ');
    expect($built)->toBe(1);

    app()->forgetScopedInstances();
    $this->tables->rates('AZ');
    expect($built)->toBe(2);
});

it('resolves a type-hinted table to the country assessing now', function () {
    expect($this->tables->within('AZ', fn () => app(RateTable::class)->rate('1', new DateTimeImmutable())->percent))->toBe(5.0)
        ->and($this->tables->within('AM', fn () => app(RateTable::class)->rate('1', new DateTimeImmutable())->percent))->toBe(18.0)
        ->and($this->tables->within('AZ', fn () => $this->tables->within('AM', fn () => $this->tables->current()) . $this->tables->current()))->toBe('AMAZ')
        ->and($this->tables->current())->toBeNull()
        ->and($this->tables->within('AM', fn () => app(TableSource::class)))->toBeInstanceOf(TableSource::class);
});

it('refuses a type-hinted table outside any tax', function () {
    app(ThresholdTable::class);
})->throws(LogicException::class, 'depends on the country');
