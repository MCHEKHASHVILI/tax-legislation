<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Actions;

use Closure;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\ValidationException;
use Mchekhashvili\Countries\Models\Country;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

/**
 * A change to the law, as a form or an agent submits it. Input is validated like a form request
 * — a ValidationException carries field errors back to the form — and the change is saved in a
 * transaction, with the editor, `reason` and `source` written to the revision log.
 *
 * rules() is public, so a FormRequest can reuse it.
 */
abstract class Action
{
    public function __construct(
        protected readonly Validation $validation,
        protected readonly ConnectionResolverInterface $db,
        protected readonly Editor $editor,
    ) {}

    /** @return array<string, mixed> */
    abstract public function rules(array $input = []): array;

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
