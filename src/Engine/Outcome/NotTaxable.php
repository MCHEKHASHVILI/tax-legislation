<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Outside the tax altogether — e.g. a company's income is profit tax, not income tax.
 * An operation can be untaxed yet still count toward a threshold: an unregistered supplier's
 * sales below 100,000 GEL carry no VAT but add up toward registration (VAT 165.1).
 */
final readonly class NotTaxable implements Outcome, CountsAsTurnover
{
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public string $reason,
        public ?Taxpayer $turnoverParty = null,
        public ?Money $turnoverAmount = null,
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function reversed(float $share): self { return $this; }
    public function turnoverOf(): ?Taxpayer { return $this->turnoverParty; }
    public function turnover(): ?Money { return $this->turnoverAmount; }

    public function __toString(): string
    {
        return "Not taxable — {$this->citation}: {$this->reason}";
    }
}
