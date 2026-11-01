<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests\Fixtures;

use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;

final readonly class Shop implements Taxpayer
{
    public function __construct(public string $id) {}
}
