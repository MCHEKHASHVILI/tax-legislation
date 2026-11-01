<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Assessments\Models;

use Mchekhashvili\TaxLegislation\Database\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * What one tax said about an assessed event: the outcome type, the article, and for a charge the
 * base, rate or per-unit duty, amount, who pays and by when.
 *
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

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}
