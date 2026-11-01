<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Ledger;

use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\TaxReport;

/** Records nothing — the default until a country's package binds its own Ledger. */
final class NullLedger implements Ledger
{
    public function record(TaxableEvent $event, TaxReport $report): void {}
}
