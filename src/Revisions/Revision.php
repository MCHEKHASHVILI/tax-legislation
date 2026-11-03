<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Revisions;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;

/**
 * One change to a legislation record, as entered: who made it, what changed, and why.
 *
 * This is the editing history — separate from the legal history the records themselves keep. A
 * rate's periods say when the law applied 18%; its revisions say that a person (or an agent)
 * entered that on a given day, from a given source, and what the record looked like before.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $action           created | updated | deleted
 * @property array{old?: array<string, mixed>, new?: array<string, mixed>} $changes
 * @property ?string $causer_type
 * @property ?int $causer_id
 * @property ?string $causer_label    who, when not a model: 'seeder', 'agent:matsne-reader'
 * @property ?string $reason
 * @property ?string $source          where the change came from: a URL, a form, a document
 * @property ?int $draft_id           the approved draft the change applied; its drafter and approver
 * @property \Carbon\CarbonImmutable $created_at
 */
final class Revision extends Model
{
    public const UPDATED_AT = null;

    protected function baseTable(): string
    {
        return 'legislation_revisions';
    }

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Draft, $this> the approved draft this change applied, if it came through one */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    /** Who, for display: the model's name or id, or the label. */
    public function who(): string
    {
        $causer = $this->causer;

        return match (true) {
            $causer !== null => (string) ($causer->getAttribute('name') ?? class_basename($causer) . ' #' . $causer->getKey()),
            $this->causer_label !== null => $this->causer_label,
            default => 'unknown',
        };
    }
}
