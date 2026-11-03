<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Revisions;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;

/**
 * Who is changing the law right now, and why. Revisions read it when a record is saved.
 *
 * By default the editor is the logged-in user. Agents and jobs say who they are:
 *
 *     app(Editor::class)->as($agent, 'Read from Matsne', source: $url)->run(fn () => ...);
 *     app(Editor::class)->as('agent:matsne-reader')->run(...);
 */
final class Editor
{
    private Model|string|null $causer = null;
    private ?string $reason = null;
    private ?string $source = null;
    private ?Draft $draft = null;

    public function __construct(private readonly Container $container) {}

    /** Who acts, and optionally why and on what source, for the changes made inside run(). */
    public function as(Model|string|null $causer, ?string $reason = null, ?string $source = null): PendingEdit
    {
        return new PendingEdit($this, $causer, $reason, $source);
    }

    /** @template T @param Closure(): T $work @return T */
    public function within(Model|string|null $causer, ?string $reason, ?string $source, Closure $work): mixed
    {
        $before = [$this->causer, $this->reason, $this->source];
        [$this->causer, $this->reason, $this->source] = [$causer ?? $this->causer, $reason, $source];

        try {
            return $work();
        } finally {
            [$this->causer, $this->reason, $this->source] = $before;
        }
    }

    /**
     * Applies an approved draft: the changes made inside are logged with the draft they came from.
     *
     * @template T @param Closure(): T $work @return T
     */
    public function applying(Draft $draft, Closure $work): mixed
    {
        $before = $this->draft;
        $this->draft = $draft;

        try {
            return $this->within(null, $draft->reason, $draft->source, $work);
        } finally {
            $this->draft = $before;
        }
    }

    /** The approved draft being applied, if any. */
    public function draft(): ?Draft
    {
        return $this->draft;
    }

    public function causer(): Model|string|null
    {
        if ($this->causer !== null) {
            return $this->causer;
        }

        if (!$this->container->bound('auth')) {
            return null;
        }

        $user = $this->container->make(Auth::class)->guard()->user();

        return $user instanceof Model ? $user : null;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function source(): ?string
    {
        return $this->source;
    }
}
