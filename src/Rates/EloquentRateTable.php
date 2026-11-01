<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Rate;
use OutOfBoundsException;

/**
 * Rates from the tax_rates table, as they stood on the event's day — the history the law has
 * actually had, not just today's numbers. Rates are found by key (the article) among one
 * country's taxes.
 */
final class EloquentRateTable implements RateTable
{
    private ?int $countryId = null;

    public function __construct(private readonly string $country) {}

    public function rate(string $article, DateTimeImmutable $on): Rate
    {
        $row = TaxRate::query()
            ->where('key', $article)
            ->whereNotNull('percent')
            ->whereHas('taxType', fn ($q) => $q->where('country_id', $this->countryId ??= Country::code($this->country)->getKey()))
            ->inForceOn($on)
            ->first();

        return $row?->rate() ?? throw new OutOfBoundsException("{$this->country}: no rate for Art. $article in force on {$on->format('Y-m-d')}.");
    }
}
