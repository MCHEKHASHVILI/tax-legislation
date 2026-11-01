<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * The tax was in force on the event's day, but no engine computes it — a tax recorded in the
 * law's history (a past social tax, say) with no Tax class registered. The report shows it so it
 * isn't silently missed.
 */
final readonly class NotComputed implements Outcome
{
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public string $reason = 'In force on this day, but no engine computes it.',
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function reversed(float $share): self { return $this; }

    public function __toString(): string
    {
        return "Not computed — {$this->citation}: {$this->reason}";
    }
}
