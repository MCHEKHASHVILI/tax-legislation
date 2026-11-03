<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;
use Mchekhashvili\TaxLegislation\Events\DraftSubmitted;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\ValueKind;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

/**
 * A change to the law, as a form or an agent submits it. Input is validated like a form request
 * — a ValidationException carries field errors back to the form.
 *
 * While approval is on (config `tax-legislation.approval.required`), the change is saved as a
 * pending Draft with no legal effect, and handle() returns the Draft. ApproveDraft re-checks it
 * against the law as it stands then and applies it. With approval off, or through directly(), the
 * change is applied at once, in a transaction, and handle() returns the record.
 *
 * Either way the editor, `reason` and `source` go to the revision log. rules() is public, so a
 * FormRequest can reuse it.
 */
abstract class Action
{
    private bool $directly = false;

    public function __construct(
        protected readonly Validation $validation,
        protected readonly ConnectionResolverInterface $db,
        protected readonly Editor $editor,
        protected readonly Dispatcher $events,
    ) {}

    /** @return array<string, mixed> */
    abstract public function rules(array $input = []): array;

    /** Makes the change in the law. @param array<string, mixed> $valid */
    abstract protected function apply(array $valid): Model;

    /** The country the change belongs to. @param array<string, mixed> $valid */
    abstract protected function countryOf(array $valid): ?int;

    /**
     * Skip approval: the change takes effect at once, as with approval off. For seeders, imports
     * and code that has already been reviewed — not for form input.
     */
    public function directly(): static
    {
        $action = clone $this;
        $action->directly = true;

        return $action;
    }

    /**
     * Validates the input against the law as it stands now: the rules, and any check beyond them.
     * Runs on submission and again on approval.
     *
     * @return array<string, mixed> validated input
     */
    protected function check(array $input): array
    {
        return $this->validate($input);
    }

    /** The record the change is made to — the value repealed, the tax abolished — if any. */
    protected function subject(): ?Model
    {
        return null;
    }

    /** What kind of value the change amends, if it amends one. */
    protected function kind(): ?ValueKind
    {
        return null;
    }

    /** Puts back the subject and kind a draft was made for, before it is re-checked and applied. */
    protected function restore(Draft $draft): void {}

    /** Validates, then applies the change or saves it as a draft. */
    protected function submit(array $input): Model|Draft
    {
        $valid = $this->check($input);

        if ($this->directly || !config('tax-legislation.approval.required', true)) {
            return $this->save($valid, fn () => $this->apply($valid));
        }

        $causer = $this->editor->causer();
        $draft = new Draft([
            'country_id' => $this->countryOf($valid),
            'action' => static::class,
            'kind' => $this->kind(),
            'payload' => array_diff_key($valid, array_flip(['reason', 'source'])),
            'status' => DraftStatus::Pending,
            'reason' => $valid['reason'] ?? null,
            'source' => $valid['source'] ?? null,
            'drafted_by_label' => is_string($causer) ? $causer : null,
        ]);

        if (($subject = $this->subject()) !== null) {
            $draft->subject()->associate($subject);
        }

        if ($causer instanceof Model) {
            $draft->draftedBy()->associate($causer);
        }

        $this->save($valid, fn () => $draft->save());
        $this->events->dispatch(new DraftSubmitted($draft));

        return $draft;
    }

    /**
     * The draft's change, re-checked against the law as it stands now and applied. ApproveDraft
     * calls this inside its transaction; a ValidationException leaves the draft pending.
     *
     * @internal
     */
    final public function applyDraft(Draft $draft): Model
    {
        $this->restore($draft);

        return $this->apply($this->check([...$draft->payload, 'reason' => $draft->reason, 'source' => $draft->source]));
    }

    /** @return array<string, mixed> validated input */
    protected function validate(array $input): array
    {
        return $this->validation->make($input, [
            ...$this->rules($input),
            'reason' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:2000'],
        ])->validate();
    }

    /** @template T @param array<string, mixed> $valid @param Closure(): T $work @return T */
    protected function save(array $valid, Closure $work): mixed
    {
        return $this->editor->within(null, $valid['reason'] ?? null, $valid['source'] ?? null,
            fn () => $this->db->connection(new LegalAct()->getConnectionName())->transaction($work));
    }

    /** A rule: the legal act must belong to the given country. */
    protected function actOfCountry(?int $countryId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($countryId) {
            if ($value !== null && LegalAct::query()->whereKey($value)->value('country_id') !== $countryId) {
                $fail("The :attribute must be an act of the same country.");
            }
        };
    }

    protected function countryId(?string $alpha2): ?int
    {
        return $alpha2 === null ? null : Country::query()->where('alpha2', strtoupper($alpha2))->value('id');
    }

    /** @param array<string, string> $messages */
    protected function fail(array $messages): never
    {
        throw ValidationException::withMessages($messages);
    }
}
