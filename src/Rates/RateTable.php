<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\TaxLegislation\Rate;

/** Rates keyed by the article that sets them, valid on a date — an amendment is a new row, not a code change. */
interface RateTable
{
    public function rate(string $article, DateTimeImmutable $on): Rate;
}
