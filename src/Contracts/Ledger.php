<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Contracts;

use Mchekhashvili\TaxLegislation\Engine\TaxReport;

/**
 * Where the engine records what it assessed, so later events can read year-to-date totals. Each
 * country's package extends it with the totals its thresholds need.
 */
interface Ledger
{
    public function record(TaxableEvent $event, TaxReport $report): void;
}
