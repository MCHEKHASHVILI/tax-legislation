<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use Closure;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Each country's rates, thresholds and tables. Two countries' packages in one app each keep their
 * own: Georgia's rates never answer for Armenia's sales tax.
 *
 * A country package registers its sources from its service provider:
 *
 *     $this->callAfterResolving(CountryTables::class, fn (CountryTables $tables) => $tables->register('GE',
 *         rates: fn () => ConfigRateTable::standard(...),
 *         thresholds: fn () => new EloquentThresholdTable('GE'),
 *     ));
 *
 * A source a country doesn't register is read from tax-legislation's history tables
 * (EloquentRateTable, EloquentThresholdTable, EloquentTableSource). Each source is built once per
 * request (a scoped binding), so a long-running worker reads amendments entered since.
 *
 * While a tax assesses, its country is the current one: a stage or handler that type-hints
 * RateTable, ThresholdTable or TableSource gets that country's.
 */
final class CountryTables
{
    private const KINDS = [RateTable::class => 'rates', ThresholdTable::class => 'thresholds', TableSource::class => 'tables'];

    /** @var list<string> */
    private array $current = [];

    public function __construct(private readonly Container $app) {}

    /**
     * @param Closure(Container): RateTable|RateTable|null           $rates
     * @param Closure(Container): ThresholdTable|ThresholdTable|null $thresholds
     * @param Closure(Container): TableSource|TableSource|null       $tables
     */
    public function register(
        string $country,
        Closure|RateTable|null $rates = null,
        Closure|ThresholdTable|null $thresholds = null,
        Closure|TableSource|null $tables = null,
    ): self {
        foreach (['rates' => $rates, 'thresholds' => $thresholds, 'tables' => $tables] as $kind => $source) {
            if ($source !== null) {
                $this->app->scoped($this->key($country, $kind), $source instanceof Closure ? $source : static fn () => $source);
            }
        }

        return $this;
    }

    public function rates(string $country): RateTable
    {
        return $this->resolve($country, 'rates', static fn () => new EloquentRateTable(strtoupper($country)));
    }

    public function thresholds(string $country): ThresholdTable
    {
        return $this->resolve($country, 'thresholds', static fn () => new EloquentThresholdTable(strtoupper($country)));
    }

    public function tables(string $country): TableSource
    {
        return $this->resolve($country, 'tables', static fn () => new EloquentTableSource(strtoupper($country)));
    }

    /**
     * Run $callback with $country as the current country.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function within(string $country, Closure $callback): mixed
    {
        $this->current[] = strtoupper($country);

        try {
            return $callback();
        } finally {
            array_pop($this->current);
        }
    }

    /** The country of the tax assessing now, or null outside any tax. */
    public function current(): ?string
    {
        return $this->current === [] ? null : $this->current[array_key_last($this->current)];
    }

    /**
     * The current country's source for a contract — what a type-hinted RateTable, ThresholdTable or
     * TableSource resolves to.
     *
     * @param class-string<RateTable|ThresholdTable|TableSource> $contract
     */
    public function forCurrent(string $contract): RateTable|ThresholdTable|TableSource
    {
        $kind = self::KINDS[$contract] ?? throw new LogicException("$contract is not a country source.");
        $country = $this->current() ?? throw new LogicException(
            "$contract depends on the country. Outside a tax, resolve it with CountryTables::$kind('GE') for the country you mean.",
        );

        return $this->{$kind}($country);
    }

    private function resolve(string $country, string $kind, Closure $default): RateTable|ThresholdTable|TableSource
    {
        $key = $this->key($country, $kind);

        if (!$this->app->bound($key)) {
            $this->app->scoped($key, $default);
        }

        return $this->app->make($key);
    }

    private function key(string $country, string $kind): string
    {
        return 'tax-legislation.tables.' . strtoupper($country) . ".$kind";
    }
}
