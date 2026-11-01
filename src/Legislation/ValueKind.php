<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation;

use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;

/** The kinds of dated values the law has — what a form amends. */
enum ValueKind: string
{
    case Rate = 'rate';
    case Threshold = 'threshold';
    case Exemption = 'exemption';
    case TableEntry = 'table';

    /** @return class-string<TaxRate|Threshold|Exemption|TableEntry> */
    public function model(): string
    {
        return match ($this) {
            self::Rate => TaxRate::class,
            self::Threshold => Threshold::class,
            self::Exemption => Exemption::class,
            self::TableEntry => TableEntry::class,
        };
    }
}
