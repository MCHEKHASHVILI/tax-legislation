<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/**
 * What the recorded law says on a day, for the engine: which taxes were in force, whether an
 * exemption was. Answers are remembered for the request and forgotten when a record changes.
 *
 * Where a country has no law recorded yet, every question answers "nothing recorded" (null, or
 * true for exemptions), so the engine runs as it would without a database.
 */
final class Law
{
    /** @var array<string, mixed> */
    private array $memo = [];

    /** The country's tax types in force on a day, by code — null when it has none recorded. @return ?Collection<string, TaxType> */
    public function taxesInForce(string $country, DateTimeInterface $on): ?Collection
    {
        if (!$this->recorded()) {
            return null;
        }

        return $this->remember("taxes|$country|{$on->format('Y-m-d')}", function () use ($country, $on) {
            $id = $this->countryId($country);

            if ($id === null || !TaxType::query()->where('country_id', $id)->exists()) {
                return null;
            }

            return TaxType::query()->where('country_id', $id)->inForceOn($on)->get()->keyBy('code');
        });
    }

    /**
     * Whether an exemption, named by its article, was in force. An article the recorded law
     * doesn't list is not contradicted: true. Its conditions are for the code to check.
     */
    public function exemptionInForce(string $country, string $tax, string $article, DateTimeInterface $on): bool
    {
        if (!$this->recorded()) {
            return true;
        }

        $periods = $this->remember("exemptions|$country|$tax", fn () => Exemption::query()
            ->whereHas('taxType', fn ($q) => $q->where('country_id', $this->countryId($country) ?? 0)->where('code', $tax))
            ->get(['key', 'effective_from', 'effective_until'])
            ->groupBy('key'));

        $rows = $periods->get($article);

        return $rows === null || $rows->contains(fn (Exemption $e) => $e->isInForceOn($on));
    }

    /** Forget every remembered answer — after the law's records change. */
    public function forget(): void
    {
        $this->memo = [];
    }

    /** Whether this database has the law's tables at all — an app may run the engine without them. */
    private function recorded(): bool
    {
        return $this->remember('recorded', function () {
            $schema = Schema::connection(config('tax-legislation.database.connection'));

            return $schema->hasTable(config('tax-legislation.database.table_prefix', '') . 'tax_types')
                && $schema->hasTable(config('countries.table', 'countries'));
        });
    }

    private function countryId(string $alpha2): ?int
    {
        return $this->remember("country|$alpha2", fn () => Country::query()->where('alpha2', strtoupper($alpha2))->value('id'));
    }

    /** @template T @param callable(): T $compute @return T */
    private function remember(string $key, callable $compute): mixed
    {
        return array_key_exists($key, $this->memo) ? $this->memo[$key] : $this->memo[$key] = $compute();
    }
}
