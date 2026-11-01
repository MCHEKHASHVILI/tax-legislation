<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests\Fixtures;

use Closure;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Stage\FirstMatch;
use Mchekhashvili\TaxLegislation\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Rates\RateTable;

/** A made-up Armenian sales tax: food exempt, everything else at the rate the RateTable gives for '1'. */
final class SalesTax extends Tax
{
    public function name(): string { return 'sales'; }
    public function country(): string { return 'AM'; }
    public function currency(): string { return 'AMD'; }

    protected function stages(): array
    {
        return [
            OnlySales::class,
            ApplySalesRules::class => [FoodIsExempt::class, StandardSalesRate::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return $event;
    }
}

final class OnlySales
{
    public function handle(object $event, Closure $next): Outcome
    {
        return $event instanceof Sale ? $next($event) : new NotTaxable('sales', new Citation('1'), 'Not a sale.');
    }
}

final class ApplySalesRules extends FirstMatch
{
    protected function ask(object $handler, object $subject): ?Outcome
    {
        return $handler->assess($subject);
    }
}

final class FoodIsExempt
{
    public function assess(Sale $sale): ?Outcome
    {
        return $sale->food ? new Exempt('sales', new Citation('2'), 'Food.') : null;
    }
}

final class StandardSalesRate
{
    public function __construct(private RateTable $rates) {}   // resolved by the container

    public function assess(Sale $sale): Outcome
    {
        $rate = $this->rates->rate('1', $sale->on);

        return new Taxable('sales', new Citation('1', 'Sales tax'), $sale->seller, $sale->price, $rate,
            $sale->price->percent($rate->percent), Collection::MonthlyDeclaration, null, $sale->on);
    }
}
