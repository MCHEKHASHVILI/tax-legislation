<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * The Code hands this case to another act (a Government resolution or a Minister's order).
 * Returned instead of guessing, so the caller knows to handle it outside the engine.
 */
final readonly class Unresolved implements Outcome
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
        return "Unresolved — {$this->citation}: {$this->reason}";
    }
}
