<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests\Fixtures;

use DateTimeImmutable;
use Mchekhashvili\TaxLegislation\Contracts\Reverses;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;

final readonly class Cancellation implements Reverses
{
    public function __construct(public Sale $sale, public float $part = 1.0) {}

    public function original(): TaxableEvent { return $this->sale; }
    public function share(): float { return $this->part; }
    public function date(): DateTimeImmutable { return $this->sale->on; }
}
