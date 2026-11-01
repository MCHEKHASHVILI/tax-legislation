<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use Mchekhashvili\Money\Currency;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;

/**
 * Everything every tax said about one event. Immutable: with() returns a new report.
 *
 * Macroable, so a country's package can add the questions its law asks of a report:
 *
 *     TaxReport::macro('vatCharged', fn () => ...);
 *
 * @implements Arrayable<string, mixed>
 */
final class TaxReport implements Arrayable, JsonSerializable
{
    use Macroable;

    /** @var Collection<string, Outcome> keyed by tax name */
    public readonly Collection $outcomes;

    /** @param iterable<Outcome> $outcomes */
    public function __construct(
        public readonly TaxableEvent $event,
        private readonly Currency|string $currency,
        iterable $outcomes = [],
    ) {
        $this->outcomes = new Collection($outcomes)->keyBy(fn (Outcome $o) => $o->tax());
    }

    public function with(Outcome $outcome): self
    {
        return new self($this->event, $this->currency, [...$this->outcomes->all(), $outcome->tax() => $outcome]);
    }

    public function for(string $tax): ?Outcome
    {
        return $this->outcomes->get($tax);
    }

    /** Outcomes that charge money — Taxable and Levied. @return Collection<string, Taxable|Levied> */
    public function charges(): Collection
    {
        return $this->outcomes->filter(fn (Outcome $o) => $o instanceof Taxable || $o instanceof Levied);
    }

    public function totalTax(): Money
    {
        return $this->amountOf(...$this->outcomes->keys()->all());
    }

    /** The amount the named taxes charged. */
    public function amountOf(string ...$taxes): Money
    {
        return $this->charges()
            ->only($taxes)
            ->reduce(fn (Money $total, Taxable|Levied $o) => $total->plus($o->amount), Money::zero($this->currency));
    }

    /** @return array{event: string, outcomes: array<string, string>, total_tax: string} */
    public function toArray(): array
    {
        return [
            'event' => $this->event::class,
            'outcomes' => $this->outcomes->map(fn (Outcome $o) => (string) $o)->all(),
            'total_tax' => (string) $this->totalTax(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
