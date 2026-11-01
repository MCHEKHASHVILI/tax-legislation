<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests\Fixtures;

use DateTimeImmutable;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;

/** A made-up country's event: a shop sells something. */
final readonly class Sale implements TaxableEvent
{
    public function __construct(public Shop $seller, public Money $price, public DateTimeImmutable $on, public bool $food = false) {}

    public function date(): DateTimeImmutable
    {
        return $this->on;
    }
}
