<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Revisions;

use Closure;
use Illuminate\Database\Eloquent\Model;

final readonly class PendingEdit
{
    public function __construct(
        private Editor $editor,
        private Model|string|null $causer,
        private ?string $reason,
        private ?string $source,
    ) {}

    /** @template T @param Closure(): T $work @return T */
    public function run(Closure $work): mixed
    {
        return $this->editor->within($this->causer, $this->reason, $this->source, $work);
    }
}
