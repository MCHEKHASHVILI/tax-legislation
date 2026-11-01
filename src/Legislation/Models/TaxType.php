<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Revisions\RecordsRevisions;
use Mchekhashvili\TaxLegislation\Engine\Tax;
use Mchekhashvili\TaxLegislation\Legislation\TaxLevel;

/**
 * One kind of tax in one country, for as long as it existed — VAT, a social tax, a turnover tax.
 *
 * Taxes come and go. Each row is one tax over one period: in force from `in_force_from` until the
 * day before `abolished_on`. Open-ended dates mean "still in force" (`abolished_on` null) and "since
 * before the history recorded here" (`in_force_from` null). A tax abolished and later brought back
 * gets a new row with the same code. A tax that took over from another names it in `replaces_id`.
 *
 * `engine` names the Mchekhashvili\TaxLegislation\Engine\Tax class that computes it, if any.
 *
 * @property int $id
 * @property int $country_id
 * @property string $code
 * @property array<string, string> $names     {"ka": "...", "en": "..."}
 * @property TaxLevel $level
 * @property ?\Carbon\CarbonImmutable $in_force_from
 * @property ?\Carbon\CarbonImmutable $abolished_on
 * @property ?int $replaces_id
 * @property ?int $introduced_by_id
 * @property ?int $abolished_by_id
 * @property ?class-string<Tax> $engine
 * @property ?string $legal_basis              the provision that establishes it: 'Art. 6.5.ა'
 * @property ?string $notes
 * @property-read string $name
 */
final class TaxType extends Model
{
    use RecordsRevisions;

    protected function baseTable(): string
    {
        return 'tax_types';
    }

    protected function casts(): array
    {
        return [
            'names' => 'array',
            'level' => TaxLevel::class,
            'in_force_from' => 'immutable_date',
            'abolished_on' => 'immutable_date',
        ];
    }

    /* ── Relations ── */

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<self, $this> the tax this one took over from */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /** @return HasMany<self, $this> */
    public function replacedBy(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_id');
    }

    /** @return BelongsTo<LegalAct, $this> */
    public function introducedBy(): BelongsTo
    {
        return $this->belongsTo(LegalAct::class, 'introduced_by_id');
    }

    /** @return BelongsTo<LegalAct, $this> */
    public function abolishedBy(): BelongsTo
    {
        return $this->belongsTo(LegalAct::class, 'abolished_by_id');
    }

    /** @return HasMany<TaxRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /** @return HasMany<Threshold, $this> */
    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class);
    }

    /** @return HasMany<Exemption, $this> */
    public function exemptions(): HasMany
    {
        return $this->hasMany(Exemption::class);
    }

    /** @return HasMany<TableEntry, $this> */
    public function tableEntries(): HasMany
    {
        return $this->hasMany(TableEntry::class);
    }

    /* ── Scopes ── */

    /** @param Builder<self> $query */
    public function scopeOfCountry(Builder $query, Country|string $country): void
    {
        $query->where('country_id', $country instanceof Country ? $country->getKey() : Country::code($country)->getKey());
    }

    /** @param Builder<self> $query */
    public function scopeInForceOn(Builder $query, DateTimeInterface|string $day): void
    {
        $day = $day instanceof DateTimeInterface ? $day->format('Y-m-d') : $day;

        $query
            ->where(fn (Builder $q) => $q->whereNull('in_force_from')->orWhereDate('in_force_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('abolished_on')->orWhereDate('abolished_on', '>', $day));
    }

    /** @param Builder<self> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->inForceOn(now());
    }

    /** @param Builder<self> $query */
    public function scopeOfLevel(Builder $query, TaxLevel $level): void
    {
        $query->where('level', $level);
    }

    /** Every period a tax code has existed, oldest first. @param Builder<self> $query */
    public function scopeHistoryOf(Builder $query, string $code): void
    {
        $query->where('code', $code)->orderByRaw('in_force_from is not null')->orderBy('in_force_from');
    }

    /* ── Questions ── */

    /** The name in the app locale, falling back to English. @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->nameIn(app()->getLocale()));
    }

    public function nameIn(string $locale): string
    {
        $names = $this->names ?? [];

        return $names[$locale] ?? $names[strtok($locale, '_-')] ?? $names['en'] ?? $this->code;
    }

    public function isInForceOn(DateTimeInterface $day): bool
    {
        $d = $day->format('Y-m-d');

        return ($this->in_force_from === null || $this->in_force_from->format('Y-m-d') <= $d)
            && ($this->abolished_on === null || $this->abolished_on->format('Y-m-d') > $d);
    }

    public function isAbolished(): bool
    {
        return $this->abolished_on !== null && $this->abolished_on->isPast();
    }

    /** The chain of predecessors, nearest first: VAT → turnover tax → … */
    public function predecessors(): Collection
    {
        $chain = new Collection();

        for ($tax = $this->replaces; $tax !== null && !$chain->contains($tax); $tax = $tax->replaces) {
            $chain->push($tax);
        }

        return $chain;
    }

    /** The law abolished the tax from a day. */
    public function abolish(DateTimeInterface|string $on, ?LegalAct $by = null): self
    {
        $this->forceFill(['abolished_on' => $on, 'abolished_by_id' => $by?->getKey()])->save();

        return $this;
    }

    /** The rate a key had on a day. */
    public function rateOn(string $key, DateTimeInterface|string $day): ?TaxRate
    {
        return $this->rates()->where('key', $key)->inForceOn($day)->first();
    }

    /** The threshold a key had on a day. */
    public function thresholdOn(string $key, DateTimeInterface|string $day): ?Threshold
    {
        return $this->thresholds()->where('key', $key)->inForceOn($day)->first();
    }

    /** A whole table as it stood on a day. @return \Illuminate\Database\Eloquent\Collection<int, TableEntry> */
    public function tableOn(string $table, DateTimeInterface|string $day): Collection
    {
        return $this->tableEntries()->where('table', $table)->inForceOn($day)->get();
    }
}
