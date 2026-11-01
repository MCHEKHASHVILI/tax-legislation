<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Mchekhashvili\Money\Currency;
use InvalidArgumentException;
use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotComputed;
use Mchekhashvili\TaxLegislation\Law\Citation;
use Mchekhashvili\TaxLegislation\Legislation\Law;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;
use Mchekhashvili\TaxLegislation\Jobs\AssessTaxableEvent;

/**
 * Runs an event through every tax, records it in the ledger, and tells the app.
 *
 *     Taxes::process($event)         assess, record, fire TaxableEventAssessed
 *     Taxes::assess($event)          a quote: assess only — nothing recorded, nothing fired
 *     Taxes::queue($event)           process on the queue
 *     Taxes::forCountry('GE')        an engine with one country's taxes
 *     Taxes::only('vat', 'income')   an engine with just these taxes
 *
 * Taxes run in registration order; each sees what the earlier ones decided.
 */
final readonly class TaxEngine
{
    /** @var Collection<string, Tax> keyed by tax name */
    private Collection $taxes;

    /** @param iterable<Tax> $taxes */
    public function __construct(
        private Container $container,
        private Ledger $ledger,
        private Events $events,
        private Bus $bus,
        iterable $taxes,
    ) {
        $this->taxes = new Collection($taxes)->keyBy(fn (Tax $tax) => $tax->name());
    }

    public function process(TaxableEvent $event): TaxReport
    {
        $report = $this->assess($event);

        $this->ledger->record($event, $report);
        $this->events->dispatch(new TaxableEventAssessed($report));

        return $report;
    }

    /**
     * What every tax says, without recording the event — a quote, a preview, a what-if.
     *
     * Where a country's law is recorded, only taxes in force on the event's day run, and a tax in
     * force that no engine computes is reported as NotComputed. Turn it off with
     * config('tax-legislation.follow_law').
     */
    public function assess(TaxableEvent $event): TaxReport
    {
        [$taxes, $uncomputed] = $this->inForce($event);

        $report = new Pipeline($this->container)
            ->send(new TaxReport($event, $this->currency()))
            ->through($taxes)
            ->thenReturn();

        return array_reduce($uncomputed, fn (TaxReport $r, NotComputed $o) => $r->with($o), $report);
    }

    /** @return array{0: list<Tax>, 1: list<NotComputed>} */
    private function inForce(TaxableEvent $event): array
    {
        if (!$this->container->make('config')->get('tax-legislation.follow_law', true)) {
            return [$this->taxes->values()->all(), []];
        }

        $law = $this->container->make(Law::class);
        $taxes = [];
        $uncomputed = [];

        foreach ($this->taxes->groupBy(fn (Tax $t) => $t->country()) as $country => $countryTaxes) {
            $recorded = $law->taxesInForce((string) $country, $event->date());

            foreach ($countryTaxes as $tax) {
                if ($recorded === null || $recorded->has($tax->name())) {
                    $taxes[] = $tax;
                }
            }

            foreach ($recorded ?? [] as $code => $type) {
                if ($type->engine === null || !class_exists($type->engine)) {
                    $uncomputed[] = new NotComputed((string) $code, new Citation(preg_replace('/^Art\.\s*/', '', (string) $type->legal_basis), $type->nameIn('en')));
                }
            }
        }

        return [$taxes, $uncomputed];
    }

    /** Process on the queue. */
    public function queue(TaxableEvent $event): mixed
    {
        return $this->bus->dispatch(new AssessTaxableEvent($event));
    }

    /** The same engine with one country's taxes. */
    public function forCountry(string $alpha2): self
    {
        return $this->with($this->taxes->filter(fn (Tax $tax) => $tax->country() === strtoupper($alpha2)));
    }

    /** The same engine with only some taxes, in their order. */
    public function only(string ...$names): self
    {
        $unknown = array_diff($names, $this->taxes->keys()->all());

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown tax: ' . implode(', ', $unknown) . '. Known: ' . $this->taxes->keys()->implode(', ') . '.');
        }

        return $this->with($this->taxes->only($names));
    }

    public function tax(string $name): Tax
    {
        return $this->taxes->get($name) ?? throw new InvalidArgumentException("Unknown tax: $name.");
    }

    /** @return Collection<string, Tax> */
    public function taxes(): Collection
    {
        return $this->taxes;
    }

    public function ledger(): Ledger
    {
        return $this->ledger;
    }

    /** The currency of the first tax; a report with no taxes has none. */
    private function currency(): Currency
    {
        return Currency::of($this->taxes->first()?->currency() ?? Currency::custom('XXX', 0, 'No currency'));
    }

    /** @param Collection<string, Tax> $taxes */
    private function with(Collection $taxes): self
    {
        return new self($this->container, $this->ledger, $this->events, $this->bus, $taxes);
    }
}
