<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Assessments\Models;

use Mchekhashvili\TaxLegislation\Database\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * What one tax said about an assessed event: the outcome type, the article, and for a charge the
 * base, rate or per-unit duty, amount, who pays and by when.
 *
 * @property ?string $country  ISO 3166-1 alpha-2 of the law that gave it
 * @property string $tax
 * @property string $outcome   taxable, levied, exempt, exempt_with_credit, not_taxable, unresolved
 * @property string $article
 */
final class AssessedOutcome extends Model
{
    protected function baseTable(): string { return 'tax_assessment_outcomes'; }

    protected function casts(): array
    {
        return [
            'base' => MoneyCast::class,
            'amount' => MoneyCast::class,
            'rate' => 'float',
            'quantity' => 'float',
            'due_on' => 'immutable_date',
            'notes' => 'array',
        ];
    }

    /** Outcomes under one country's law: a Country, or its alpha-2, alpha-3 or numeric code. */
    public function scopeOfCountry(Builder $query, Country|string $country): void
    {
        $query->where('country', Assessment::alpha2($country));
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}
