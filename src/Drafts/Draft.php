<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Drafts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Actions\Action;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;
use Mchekhashvili\TaxLegislation\Revisions\RecordsRevisions;

/**
 * A change to the law as submitted through an action — a new act, a tax, an amendment, a repeal —
 * waiting for approval. A draft has no legal effect: the legislation tables don't hold it, so the
 * engine, `inForceOn()` and `Law` never see it. ApproveDraft applies it; RejectDraft keeps it, with
 * the reason, unapplied.
 *
 *     $draft = app(AmendLaw::class)->handle(ValueKind::Rate, $input);   // a Draft while approval is on
 *     $rate = app(ApproveDraft::class)->handle($draft);                  // the TaxRate, now in the law
 *
 * @property int $id
 * @property ?int $country_id
 * @property class-string<Action> $action
 * @property ?ValueKind $kind                  what AmendLaw changes
 * @property ?string $subject_type             the record a repeal or an abolition changes
 * @property ?int $subject_id
 * @property array<string, mixed> $payload     the validated input, without reason and source
 * @property DraftStatus $status
 * @property ?string $reason
 * @property ?string $source
 * @property ?string $drafted_by_type
 * @property ?int $drafted_by_id
 * @property ?string $drafted_by_label         who, when not a model: 'agent:matsne-reader'
 * @property ?string $decided_by_type
 * @property ?int $decided_by_id
 * @property ?string $decided_by_label
 * @property ?\Carbon\CarbonImmutable $decided_at
 * @property ?string $decision_note            why it was rejected, or a note on the approval
 * @property ?string $result_type              the record approval created or changed
 * @property ?int $result_id
 * @property \Carbon\CarbonImmutable $created_at
 */
final class Draft extends Model
{
    use RecordsRevisions;

    protected function baseTable(): string
    {
        return 'legislation_drafts';
    }

    protected function casts(): array
    {
        return [
            'kind' => ValueKind::class,
            'payload' => 'array',
            'status' => DraftStatus::class,
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return MorphTo<Eloquent, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Eloquent, $this> */
    public function draftedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Eloquent, $this> */
    public function decidedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Eloquent, $this> */
    public function result(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', DraftStatus::Pending);
    }

    /** @param Builder<self> $query */
    public function scopeOfCountry(Builder $query, Country|string $country): void
    {
        $query->where('country_id', $country instanceof Country ? $country->getKey() : Country::code($country)->getKey());
    }

    public function isPending(): bool
    {
        return $this->status === DraftStatus::Pending;
    }

    /** Who drafted it, for display. */
    public function drafter(): string
    {
        return self::label($this->draftedBy, $this->drafted_by_label);
    }

    /** Who approved or rejected it, for display; null while pending. */
    public function decider(): ?string
    {
        return $this->isPending() ? null : self::label($this->decidedBy, $this->decided_by_label);
    }

    private static function label(?Eloquent $model, ?string $label): string
    {
        return match (true) {
            $model !== null => (string) ($model->getAttribute('name') ?? class_basename($model) . ' #' . $model->getKey()),
            $label !== null => $label,
            default => 'unknown',
        };
    }
}
