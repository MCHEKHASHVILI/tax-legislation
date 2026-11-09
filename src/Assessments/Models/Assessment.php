<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Assessments\Models;

use Mchekhashvili\TaxLegislation\Database\Model;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * One event the engine assessed, with what every tax said about it. The event itself is kept
 * serialized, so it can be re-assessed after a rate change.
 *
 *     Assessment::ofCountry('GE')->whereYear('occurred_on', 2026)
 *
 * @property string $event_type
 * @property ?string $country    ISO 3166-1 alpha-2 of the law that assessed it; null if its outcomes span several countries
 * @property \DateTimeImmutable $occurred_on
 */
final class Assessment extends Model
{
    protected function baseTable(): string { return 'tax_assessments'; }

    protected function casts(): array
    {
        return ['occurred_on' => 'immutable_date', 'total_tax' => MoneyCast::class];
    }

    /** @return HasMany<AssessedOutcome, $this> */
    public function outcomes(): HasMany
    {
        return $this->hasMany(AssessedOutcome::class, 'assessment_id');
    }

    /** Assessments under one country's law: a Country, or its alpha-2, alpha-3 or numeric code. */
    public function scopeOfCountry(Builder $query, Country|string $country): void
    {
        $query->where('country', self::alpha2($country));
    }

    /** @internal */
    public static function alpha2(Country|string $country): string
    {
        return match (true) {
            $country instanceof Country => $country->alpha2,
            strlen($country) === 2 => strtoupper($country),
            default => Country::code($country)->alpha2,
        };
    }

    public function event(): TaxableEvent
    {
        return unserialize(base64_decode($this->payload), ['allowed_classes' => true]);
    }
}
