<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\Money\Money;

/** The amounts the law draws lines at, as they stood on a day. */
interface ThresholdTable
{
    /** @throws \OutOfBoundsException when the law has no such threshold that day */
    public function threshold(string $tax, string $key, DateTimeImmutable $on): Money;
}
