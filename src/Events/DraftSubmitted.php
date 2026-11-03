<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Events;

use Mchekhashvili\TaxLegislation\Drafts\Draft;

/** A change to the law was saved as a draft and waits for approval. */
final readonly class DraftSubmitted
{
    public function __construct(public Draft $draft) {}
}
