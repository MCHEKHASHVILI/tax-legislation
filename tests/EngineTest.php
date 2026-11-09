<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Assessments\Models\Assessment;
use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;
use Mchekhashvili\TaxLegislation\Facades\Taxes;
use Mchekhashvili\TaxLegislation\Jobs\AssessTaxableEvent;
use Mchekhashvili\TaxLegislation\Rates\ConfigRateTable;
use Mchekhashvili\TaxLegislation\Rates\CountryTables;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Cancellation;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Sale;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\SalesTax;
use Mchekhashvili\TaxLegislation\Tests\Fixtures\Shop;

/** A ledger that remembers what it was told. */
final class ListLedger implements Ledger
{
    public array $recorded = [];

    public function record(TaxableEvent $event, TaxReport $report): void
    {
        $this->recorded[] = $report;
    }
}

beforeEach(function () {
    app(TaxRegistry::class)->register(SalesTax::class);
    app(CountryTables::class)->register('AM', rates: new ConfigRateTable(['1' => ['2000-01-01' => 20, '2024-01-01' => 18]]));
    app()->instance(Ledger::class, $this->ledger = new ListLedger());

    $this->sale = fn (int $amd, string $on = '2026-10-30', bool $food = false) => new Sale(new Shop('shop'), Money::AMD($amd), new DateTimeImmutable($on), $food);
});

it('runs the registered taxes', function () {
    $report = Taxes::process(($this->sale)(1000));

    expect(Taxes::taxes()->keys()->all())->toBe(['sales'])
        ->and((string) $report->for('sales')->amount)->toBe('180.00 AMD')
        ->and((string) $report->totalTax())->toBe('180.00 AMD')
        ->and($report->toArray()['outcomes']['sales'])->toContain('Art. 1 — Sales tax');
});

it('reads the rate in force on the event day', function () {
    expect((string) Taxes::assess(($this->sale)(1000, '2020-06-01'))->for('sales')->amount)->toBe('200.00 AMD');
});

it('lets the first matching rule answer', function () {
    expect(Taxes::assess(($this->sale)(1000, food: true))->for('sales'))->toBeInstanceOf(Exempt::class);
});

it('reverses an earlier event in proportion', function () {
    expect((string) Taxes::assess(new Cancellation(($this->sale)(1000), 0.5))->for('sales')->amount)->toBe('-90.00 AMD');
});

it('records, announces and stores a processed event, and only that', function () {
    Event::fake([TaxableEventAssessed::class]);

    Taxes::assess(($this->sale)(1000));
    expect($this->ledger->recorded)->toBe([]);

    $report = Taxes::process(($this->sale)(1000));
    expect($this->ledger->recorded)->toBe([$report]);
    Event::assertDispatched(TaxableEventAssessed::class, fn ($e) => $e->report === $report);
});

it('stores reports with their outcomes', function () {
    Taxes::process(($this->sale)(1000));

    $assessment = Assessment::query()->with('outcomes')->sole();
    $outcome = $assessment->outcomes->sole();

    expect((string) $assessment->total_tax)->toBe('180.00 AMD')
        ->and($assessment->event())->toEqual(($this->sale)(1000))
        ->and([$outcome->tax, $outcome->outcome, $outcome->article, $outcome->rate, $outcome->taxpayer])->toBe(['sales', 'taxable', '1', 18.0, 'shop'])
        ->and((string) $outcome->base)->toBe('1000.00 AMD');
});

it('stores the country whose law assessed the event', function () {
    $report = Taxes::process(($this->sale)(1000));

    $assessment = Assessment::query()->with('outcomes')->sole();

    expect($report->country())->toBe('AM')
        ->and($report->countryOf('sales'))->toBe('AM')
        ->and($report->toArray()['country'])->toBe('AM')
        ->and($assessment->country)->toBe('AM')
        ->and($assessment->outcomes->sole()->country)->toBe('AM')
        ->and(Assessment::ofCountry('am')->count())->toBe(1)
        ->and(Assessment::ofCountry('GE')->count())->toBe(0)
        ->and(\Mchekhashvili\TaxLegislation\Assessments\Models\AssessedOutcome::ofCountry('AM')->count())->toBe(1);
});

it('knows no country for a report with no outcomes, or one built without them', function () {
    $empty = Taxes::forCountry('GE')->assess(($this->sale)(1));
    $bare = new TaxReport(($this->sale)(1), 'AMD', [new Exempt('sales', new \Mchekhashvili\TaxLegislation\Law\Citation('2'), 'Food.')]);

    expect($empty->country())->toBeNull()
        ->and($bare->countryOf('sales'))->toBeNull()
        ->and($bare->country())->toBeNull()
        ->and($bare->with($bare->for('sales'), 'am')->country())->toBe('AM');
});

it('narrows by country and by tax', function () {
    expect(Taxes::forCountry('am')->taxes()->keys()->all())->toBe(['sales'])
        ->and(Taxes::forCountry('GE')->taxes()->all())->toBe([])
        ->and(Taxes::forCountry('GE')->assess(($this->sale)(1))->outcomes->all())->toBe([]);

    Taxes::only('vat');
})->throws(InvalidArgumentException::class, 'Unknown tax: vat');

it('lets the app replace a tax\'s stages from config', function () {
    config()->set('tax-legislation.pipelines.sales', [
        \Mchekhashvili\TaxLegislation\Tests\Fixtures\ApplySalesRules::class => [\Mchekhashvili\TaxLegislation\Tests\Fixtures\FoodIsExempt::class, \Mchekhashvili\TaxLegislation\Tests\Fixtures\StandardSalesRate::class],
    ]);

    expect(Taxes::assess(new class implements TaxableEvent {
        public function date(): DateTimeImmutable { return new DateTimeImmutable(); }
    }))->toBeInstanceOf(TaxReport::class);
})->throws(TypeError::class);   // OnlySales removed: a non-sale now reaches the sale rules

it('lets the app choose the taxes from config', function () {
    config()->set('tax-legislation.taxes', []);
    app()->forgetInstance(TaxEngine::class);

    expect(Taxes::taxes()->all())->toBe([]);
});

it('lets country packages add questions to the report', function () {
    TaxReport::macro('salesTax', fn () => $this->amountOf('sales'));

    expect((string) Taxes::assess(($this->sale)(1000))->salesTax())->toBe('180.00 AMD');
});

it('queues an assessment', function () {
    Bus::fake();
    config()->set('tax-legislation.queue', ['connection' => 'redis', 'queue' => 'tax']);

    Taxes::queue(($this->sale)(1000));

    Bus::assertDispatched(AssessTaxableEvent::class, fn ($job) => $job->connection === 'redis' && $job->queue === 'tax');
});

it('records nothing without a country ledger', function () {
    app()->forgetInstance(Ledger::class);
    app()->bind(Ledger::class, \Mchekhashvili\TaxLegislation\Ledger\NullLedger::class);
    app()->forgetInstance(TaxEngine::class);

    expect(Taxes::ledger())->toBeInstanceOf(\Mchekhashvili\TaxLegislation\Ledger\NullLedger::class);
});
