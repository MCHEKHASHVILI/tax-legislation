<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * The answer one tax gives for one event. Always carries the article it rests on.
 * Implementations: Taxable, Exempt, NotTaxable, Unresolved.
 */
interface Outcome
{
    public function tax(): string;

    public function basis(): Citation;

    /** For a refund: the same outcome, reversed in proportion. Non-monetary outcomes return themselves. */
    public function reversed(float $share): self;
}
