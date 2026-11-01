<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Not charged, yet credit-preserving (Georgian VAT, Art. 169.3, 172): no VAT is charged, but the supplier keeps the right to credit input VAT on
 * related costs — what other systems call zero-rated. Exports and the 172.1–172.2 operations also
 * count toward the registration threshold (165.7).
 */
final readonly class ExemptWithCredit implements Outcome, CountsAsTurnover
{
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public string $reason,
        public Taxpayer $supplier,
        public Money $base,
        public bool $countsTowardThreshold = false,
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function reversed(float $share): self { return $this; }
    public function turnoverOf(): ?Taxpayer { return $this->supplier; }
    public function turnover(): ?Money { return $this->countsTowardThreshold ? $this->base : null; }

    public function __toString(): string
    {
        return "Exempt with credit — {$this->citation}: {$this->reason}";
    }
}
