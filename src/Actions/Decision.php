<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Drafts\DraftStatus;
use Mchekhashvili\TaxLegislation\Events\DraftDecided;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

/**
 * Approves or rejects a pending draft. Who may decide is the app's call: the editor (the logged-in
 * user, or whoever Editor::as() names) must pass the gate config('tax-legislation.approval.gate')
 * names, given the draft. The package defines no such gate, so until the app does, nobody may
 * decide; set the gate to null to leave authorization to your routes' middleware instead.
 *
 *     Gate::define('approve-legislation', fn (User $user, Draft $draft) => $user->is_editor && !$draft->draftedBy?->is($user));
 */
abstract class Decision
{
    public function __construct(
        protected readonly Validation $validation,
        protected readonly ConnectionResolverInterface $db,
        protected readonly Editor $editor,
        protected readonly Dispatcher $events,
        protected readonly Container $container,
    ) {}

    /** @return array<string, mixed> */
    abstract public function rules(array $input = []): array;

    /**
     * Validates, authorizes and decides, in one transaction: the draft is locked and must still be
     * pending. The decider, `decided_at` and the note are recorded on the draft and in its revision.
     *
     * @template T @param Closure(Draft): T $work @return T
     */
    protected function decide(Draft $draft, array $input, DraftStatus $status, Closure $work): mixed
    {
        $valid = $this->validation->make($input, $this->rules($input))->validate();
        $this->authorize($draft);

        $result = $this->db->connection($draft->getConnectionName())->transaction(function () use ($draft, $valid, $status, $work) {
            $locked = Draft::query()->lockForUpdate()->findOrFail($draft->getKey());

            if (!$locked->isPending()) {
                throw ValidationException::withMessages(['draft' => "This draft was already {$locked->status->value}."]);
            }

            $result = $work($locked);
            $causer = $this->editor->causer();

            $locked->forceFill([
                'status' => $status,
                'decided_at' => now(),
                'decision_note' => $valid['reason'] ?? null,
                'decided_by_label' => is_string($causer) ? $causer : null,
            ]);

            if ($causer instanceof Model) {
                $locked->decidedBy()->associate($causer);
            }

            if ($result instanceof Model) {
                $locked->result()->associate($result);
            }

            $this->editor->within(null, $valid['reason'] ?? null, null, fn () => $locked->save());
            $draft->setRawAttributes($locked->getAttributes(), sync: true)->setRelations($locked->getRelations());

            return $result;
        });

        $this->events->dispatch(new DraftDecided($draft));

        return $result;
    }

    private function authorize(Draft $draft): void
    {
        $ability = config('tax-legislation.approval.gate');

        if ($ability === null) {
            return;
        }

        if (!$this->container->bound(Gate::class)) {
            throw new LogicException("Approval goes through the gate '$ability', but no Gate is bound. Set tax-legislation.approval.gate to null to authorize elsewhere.");
        }

        $causer = $this->editor->causer();

        $this->container->make(Gate::class)->forUser($causer instanceof Model ? $causer : null)->authorize($ability, [$draft]);
    }
}
