<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use DateTimeImmutable;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * A specific duty: so much per unit — per cm³ of engine, per litre of pure alcohol, per tonne, per
 * minute — rather than a percentage of a price. Excise and parts of import tax are levied this way.
 * Where a percentage part is added (cigarettes: per 20 sticks plus 20% of retail price), the
 * amount includes it and the notes say how it was built.
 */
final readonly class Levied implements Outcome
{
    /** @param list<string> $notes */
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public Taxpayer $taxpayer,
        public float $quantity,
        public string $unit,
        public Money $ratePerUnit,
        public Money $amount,
        public Collection $collection,
        public DateTimeImmutable $due,
        public array $notes = [],
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }

    public function reversed(float $share): self
    {
        return new self(
            $this->taxName, $this->citation, $this->taxpayer, -$this->quantity * $share, $this->unit,
            $this->ratePerUnit, $this->amount->multipliedBy($share)->negated(), $this->collection, $this->due,
            [...$this->notes, 'Reversal of an earlier operation.'],
        );
    }

    public function __toString(): string
    {
        $qty = rtrim(rtrim(number_format($this->quantity, 3, '.', ''), '0'), '.');

        return "{$this->citation}: $qty {$this->unit} × {$this->ratePerUnit} = {$this->amount}, paid by {$this->taxpayer->id}, due {$this->due->format('Y-m-d')}";
    }
}
