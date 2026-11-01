<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;

/** Tables from tax_table_entries, as they stood on a day; each table and day read once per request. */
final class EloquentTableSource implements TableSource
{
    private ?int $countryId = null;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $memo = [];

    public function __construct(private readonly string $country) {}

    public function rows(string $tax, string $table, DateTimeImmutable $on): array
    {
        return $this->memo["$tax|$table|{$on->format('Y-m-d')}"] ??= TableEntry::query()
            ->inTable($table)
            ->whereHas('taxType', fn ($q) => $q->where('code', $tax)->where('country_id', $this->countryId ??= Country::code($this->country)->getKey()))
            ->inForceOn($on)
            ->orderBy('id')
            ->get(['key', 'values'])
            ->mapWithKeys(fn (TableEntry $e) => [$e->key => $e->values])
            ->all();
    }
}
