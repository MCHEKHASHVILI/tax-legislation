<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use DateTimeImmutable;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\Taxpayer;
use Mchekhashvili\TaxLegislation\Rate;
use Mchekhashvili\TaxLegislation\Law\Citation;

final readonly class Taxable implements Outcome, CountsAsTurnover
{
    /** @param list<string> $notes */
    public function __construct(
        public string $taxName,
        public Citation $citation,
        public Taxpayer $taxpayer,
        public Money $base,
        public Rate $rate,
        public Money $amount,
        public Collection $collection,
        public ?Taxpayer $agent,
        public DateTimeImmutable $due,
        public array $notes = [],
        /** For a tax with input credit (VAT): the buyer who may credit it. */
        public ?Taxpayer $creditableBy = null,
        /** Whether the base adds to the taxpayer's turnover for thresholds (VAT 165.1). */
        public bool $countsAsTurnover = true,
    ) {}

    public function tax(): string { return $this->taxName; }
    public function basis(): Citation { return $this->citation; }
    public function turnoverOf(): ?Taxpayer { return $this->taxpayer; }
    public function turnover(): ?Money { return $this->countsAsTurnover ? $this->base : null; }

    public function withBase(Money $base, string $note): self
    {
        return $this->copy(base: $base, amount: $base->percent($this->rate->percent), notes: [...$this->notes, $note]);
    }

    public function reversed(float $share): self
    {
        return $this->copy(
            base: $this->base->multipliedBy($share)->negated(),
            amount: $this->amount->multipliedBy($share)->negated(),
            notes: [...$this->notes, 'Reversal of an earlier operation.'],
        );
    }

    public function __toString(): string
    {
        $who = match (true) {
            $this->collection === Collection::ReverseCharge => "reverse-charged by {$this->agent?->id}",
            $this->agent !== null => "withheld by {$this->agent->id}",
            default => "paid by {$this->taxpayer->id}",
        };

        return "{$this->citation}: {$this->rate} of {$this->base} = {$this->amount}, $who, due {$this->due->format('Y-m-d')}";
    }

    /** @param list<string>|null $notes */
    private function copy(Money $base, Money $amount, ?array $notes = null): self
    {
        return new self(
            $this->taxName, $this->citation, $this->taxpayer, $base, $this->rate, $amount,
            $this->collection, $this->agent, $this->due, $notes ?? $this->notes,
            $this->creditableBy, $this->countsAsTurnover,
        );
    }
}
