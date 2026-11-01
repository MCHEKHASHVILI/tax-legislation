<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\ActKind;

/**
 * A legal act that made or changed tax law: the Tax Code, a law amending it, a government
 * resolution, a municipal council's act. Every tax type and every dated value points to the act
 * behind it, so one act's full effect can be listed.
 *
 *     $act->ratesSet; $act->thresholdsSet; $act->exemptionsSet; $act->tableEntriesSet;
 *     $act->taxTypesIntroduced; $act->taxTypesAbolished; $act->amendments;
 *
 * @property int $id
 * @property int $country_id
 * @property ActKind $kind
 * @property ?string $number          '3591-IIს', '6942'
 * @property string $title
 * @property ?\Carbon\CarbonImmutable $adopted_on
 * @property ?\Carbon\CarbonImmutable $published_on
 * @property ?\Carbon\CarbonImmutable $effective_on
 * @property ?string $url             e.g. a matsne.gov.ge document link
 * @property ?int $amends_id          the act this one amends
 * @property ?string $issuer          parliament, government, ministry, municipal council
 * @property ?string $notes
 */
final class LegalAct extends Model
{
    protected function baseTable(): string
    {
        return 'legal_acts';
    }

    protected function casts(): array
    {
        return [
            'kind' => ActKind::class,
            'adopted_on' => 'immutable_date',
            'published_on' => 'immutable_date',
            'effective_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<self, $this> */
    public function amends(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_id');
    }

    /** @return HasMany<self, $this> the acts that amended this one */
    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'amends_id');
    }

    /** @return HasMany<TaxType, $this> */
    public function taxTypesIntroduced(): HasMany
    {
        return $this->hasMany(TaxType::class, 'introduced_by_id');
    }

    /** @return HasMany<TaxType, $this> */
    public function taxTypesAbolished(): HasMany
    {
        return $this->hasMany(TaxType::class, 'abolished_by_id');
    }

    /** @return HasMany<TaxRate, $this> */
    public function ratesSet(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /** @return HasMany<Threshold, $this> */
    public function thresholdsSet(): HasMany
    {
        return $this->hasMany(Threshold::class);
    }

    /** @return HasMany<Exemption, $this> */
    public function exemptionsSet(): HasMany
    {
        return $this->hasMany(Exemption::class);
    }

    /** @return HasMany<TableEntry, $this> */
    public function tableEntriesSet(): HasMany
    {
        return $this->hasMany(TableEntry::class);
    }

    /** @param Builder<self> $query */
    public function scopeOfCountry(Builder $query, Country|string $country): void
    {
        $query->where('country_id', $country instanceof Country ? $country->getKey() : Country::code($country)->getKey());
    }

    /** "Law №6942 of 15.07.2020" */
    public function citation(): string
    {
        $number = $this->number === null ? '' : " №{$this->number}";
        $date = $this->adopted_on === null ? '' : ' of ' . $this->adopted_on->format('d.m.Y');

        return "{$this->title}{$number}{$date}";
    }
}
