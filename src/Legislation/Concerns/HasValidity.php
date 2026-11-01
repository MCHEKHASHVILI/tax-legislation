<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;

/**
 * A value of the law that holds for a period: a rate, a threshold, an exemption, a table row.
 *
 * In force from `effective_from` (null: since before the recorded history) until the day before
 * `effective_until` (null: still in force). `legal_act_id` and `article` say which act and which
 * provision set it; `repealed_by_id`, which act ended it. Rows are never overwritten — amend() closes the current row and opens the
 * next, so every past value stays answerable.
 *
 * The model has `tax_type_id` and `key`; the key names the value within its tax: an article
 * ('166'), a slug ('registration'), an HS code, a municipality.
 *
 * @property ?\Carbon\CarbonImmutable $effective_from
 * @property ?\Carbon\CarbonImmutable $effective_until
 * @property ?int $legal_act_id
 * @property ?int $repealed_by_id
 * @property ?string $article
 */
trait HasValidity
{
    public function initializeHasValidity(): void
    {
        $this->mergeCasts(['effective_from' => 'immutable_date', 'effective_until' => 'immutable_date']);
    }

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return BelongsTo<LegalAct, $this> the act that set this value */
    public function legalAct(): BelongsTo
    {
        return $this->belongsTo(LegalAct::class);
    }

    /** Values in force on a day. @param Builder<static> $query */
    public function scopeInForceOn(Builder $query, DateTimeInterface|string $day): void
    {
        $day = $day instanceof DateTimeInterface ? $day->format('Y-m-d') : $day;

        $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>', $day));
    }

    /** @param Builder<static> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->inForceOn(now());
    }

    /** One value's whole history, oldest first. @param Builder<static> $query */
    public function scopeHistoryOf(Builder $query, TaxType $tax, string $key): void
    {
        $query->whereBelongsTo($tax)->where('key', $key)->orderByRaw('effective_from is not null')->orderBy('effective_from');
    }

    /** @param Builder<static> $query */
    public function scopeOfTax(Builder $query, TaxType $tax): void
    {
        $query->whereBelongsTo($tax);
    }

    public function isInForceOn(DateTimeInterface $day): bool
    {
        $d = $day->format('Y-m-d');

        return ($this->effective_from === null || $this->effective_from->format('Y-m-d') <= $d)
            && ($this->effective_until === null || $this->effective_until->format('Y-m-d') > $d);
    }

    /**
     * The law changed this value from a day: close the row in force then, open a new one.
     *
     *     TaxRate::amend($vat, '166', ['percent' => 18], from: '2026-01-01', by: $law, article: '166');
     *
     * A row already starting after the day is left alone; the new row ends where it begins.
     *
     * @param array<string, mixed> $values
     */
    public static function amend(TaxType $tax, string $key, array $values, DateTimeInterface|string $from, ?LegalAct $by = null, ?string $article = null): static
    {
        $from = $from instanceof DateTimeInterface ? $from->format('Y-m-d') : $from;

        $same = fn () => static::query()->whereBelongsTo($tax)->where('key', $key)
            ->where(array_intersect_key($values, array_flip(static::identifiedBy())));

        return DB::connection(new static()->getConnectionName())->transaction(function () use ($same, $tax, $key, $values, $from, $by, $article) {
            $current = $same()->inForceOn($from)->first();
            $next = $same()->whereDate('effective_from', '>', $from)->orderBy('effective_from')->first();

            $current?->forceFill(['effective_until' => $from])->save();

            return static::query()->create([
                ...$values,
                'tax_type_id' => $tax->getKey(),
                'key' => $key,
                'effective_from' => $from,
                'effective_until' => $next?->effective_from,
                'legal_act_id' => $by?->getKey(),
                'article' => $article ?? $current?->article,
            ]);
        });
    }

    /** Columns besides tax and key that tell one value from another — a table entry's table. @return list<string> */
    protected static function identifiedBy(): array
    {
        return [];
    }

    /** The law removed this value from a day. */
    public function repeal(DateTimeInterface|string $on, ?LegalAct $by = null): static
    {
        $this->forceFill(['effective_until' => $on, 'repealed_by_id' => $by?->getKey()])->save();

        return $this;
    }

    /** @return BelongsTo<LegalAct, $this> the act that ended this value */
    public function repealedBy(): BelongsTo
    {
        return $this->belongsTo(LegalAct::class, 'repealed_by_id');
    }
}
