<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation;

/** What a threshold amount is measured over. */
enum ThresholdPeriod: string
{
    case Transaction = 'transaction';          // one payment, one supply
    case Day = 'day';
    case Month = 'month';
    case CalendarYear = 'calendar_year';
    case Rolling12Months = 'rolling_12_months';
    case Lifetime = 'lifetime';
}
