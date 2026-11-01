<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;

/** The rows of a table of the law — an excise list, land rates — as they stood on a day. */
interface TableSource
{
    /** @return array<string, array<string, mixed>> key => values */
    public function rows(string $tax, string $table, DateTimeImmutable $on): array;
}
