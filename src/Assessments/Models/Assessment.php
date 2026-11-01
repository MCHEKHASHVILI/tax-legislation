<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Assessments\Models;

use Mchekhashvili\TaxLegislation\Database\Model;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mchekhashvili\Money\Laravel\MoneyCast;

/**
 * One event the engine assessed, with what every tax said about it. The event itself is kept
 * serialized, so it can be re-assessed after a rate change.
 *
 * @property string $event_type
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

    public function event(): TaxableEvent
    {
        return unserialize(base64_decode($this->payload), ['allowed_classes' => true]);
    }
}
