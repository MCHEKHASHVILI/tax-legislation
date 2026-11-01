<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Outcome;

/**
 * How a tax reaches the budget. When it is due is the country's law: each outcome carries its own
 * due date, worked out by the country's package.
 */
enum Collection
{
    /** The payer withholds on payment and passes the tax on. */
    case WithheldAtSource;

    /** The taxpayer declares and pays for a month. */
    case MonthlyDeclaration;

    /** The taxpayer declares for a year. */
    case AnnualDeclaration;

    /** Declared and paid over several years. */
    case OverTwoYears;

    /** The customer charges itself the tax on a supply from abroad. */
    case ReverseCharge;

    /** Paid with import duties at customs. */
    case AtCustoms;

    /** On import, but paid later than release. */
    case AtCustomsDeferred;
}
