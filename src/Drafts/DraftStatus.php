<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Drafts;

enum DraftStatus: string
{
    /** Saved, with no legal effect, until someone approves or rejects it. */
    case Pending = 'pending';

    /** Applied: the change is in the law from the day it names. */
    case Approved = 'approved';

    /** Kept, with the reason, but never applied. */
    case Rejected = 'rejected';
}
