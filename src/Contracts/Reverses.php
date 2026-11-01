<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Contracts;

/**
 * An event that undoes part of an earlier one — a refund, a return, a cancellation. Every tax
 * answers it with its outcome for the original, reversed in proportion.
 */
interface Reverses extends TaxableEvent
{
    public function original(): TaxableEvent;

    /** How much of the original is undone: 1.0 is all of it. */
    public function share(): float;
}
