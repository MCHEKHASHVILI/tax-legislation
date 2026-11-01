<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;

/**
 * An outcome that adds to its taxpayer's turnover for a threshold — e.g. the 100,000 GEL VAT
 * registration line counts taxable supplies even before the supplier is registered (165.1, 165.7).
 */
interface CountsAsTurnover
{
    public function turnoverOf(): ?Taxpayer;

    /** Null when nothing counts. */
    public function turnover(): ?Money;
}
