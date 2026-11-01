<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\TaxLegislation\Law\Citation;

/** In scope of the tax, but released from it by a specific article. */
final readonly class Exempt implements Outcome
{
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public string $reason,
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function reversed(float $share): self { return $this; }

    public function __toString(): string
    {
        return "Exempt — {$this->citation}: {$this->reason}";
    }
}
