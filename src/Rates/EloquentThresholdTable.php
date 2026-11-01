<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;
use OutOfBoundsException;

/** Thresholds from the tax_thresholds table, as they stood on the day. */
final class EloquentThresholdTable implements ThresholdTable
{
    private ?int $countryId = null;

    public function __construct(private readonly string $country) {}

    public function threshold(string $tax, string $key, DateTimeImmutable $on): Money
    {
        $row = Threshold::query()
            ->where('key', $key)
            ->whereHas('taxType', fn ($q) => $q->where('code', $tax)->where('country_id', $this->countryId ??= Country::code($this->country)->getKey()))
            ->inForceOn($on)
            ->first();

        return $row?->amount ?? throw new OutOfBoundsException("{$this->country}: no $tax threshold '$key' in force on {$on->format('Y-m-d')}.");
    }
}
