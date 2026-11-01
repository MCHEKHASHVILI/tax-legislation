<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Contracts;

use DateTimeImmutable;

/**
 * Something that happened which a tax may care about: a payment, a sale, an import, holding
 * property. Each country's package defines its events; the engine only needs to know when.
 */
interface TaxableEvent
{
    /** The day the law in force is read for. */
    public function date(): DateTimeImmutable;
}
