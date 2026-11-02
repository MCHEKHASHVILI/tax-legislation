<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests\Fixtures;

use Closure;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;

/**
 * A made-up Azerbaijani turnover tax — a second country beside SalesTax, keyed by the same article
 * '1'. Sales below the 'small' threshold are not taxed. It reads its tables through the Tax, not
 * through the container, the other way a country package can.
 */
final class TurnoverTax extends Tax
{
    public function name(): string { return 'turnover'; }
    public function country(): string { return 'AZ'; }
    public function currency(): string { return 'AZN'; }

    protected function stages(): array
    {
        return [TurnoverRule::class];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Turnover($event, $this->rateTable(), $this->thresholdTable());
    }
}

final readonly class Turnover
{
    public function __construct(public Sale $sale, public RateTable $rates, public ThresholdTable $thresholds) {}
}

final class TurnoverRule
{
    public function handle(Turnover $t, Closure $next): Outcome
    {
        $sale = $t->sale;

        if ($sale->price->toFloat() < $t->thresholds->threshold('turnover', 'small', $sale->on)->toFloat()) {
            return new NotTaxable('turnover', new Citation('2'), 'Below the small-sale threshold.');
        }

        $rate = $t->rates->rate('1', $sale->on);

        return new Taxable('turnover', new Citation('1', 'Turnover tax'), $sale->seller, $sale->price, $rate,
            $sale->price->percent($rate->percent), Collection::MonthlyDeclaration, null, $sale->on);
    }
}
