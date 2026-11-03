<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Events;

use Mchekhashvili\TaxLegislation\Drafts\Draft;

/** A draft was approved (and applied) or rejected; `$draft->status` says which. */
final readonly class DraftDecided
{
    public function __construct(public Draft $draft) {}
}
