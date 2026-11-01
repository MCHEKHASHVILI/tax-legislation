<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Contracts;

/** Whoever pays or withholds a tax. A country's package decides what else a party carries. */
interface Taxpayer
{
    /** A stable identifier: your customer id, a tax number. */
    public string $id { get; }
}
