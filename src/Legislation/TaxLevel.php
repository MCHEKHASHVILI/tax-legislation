<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation;

/** Who levies a tax. Georgia has national and local taxes (Tax Code Art. 6.2); federal states add a middle tier. */
enum TaxLevel: string
{
    case National = 'national';
    case Regional = 'regional';
    case Local = 'local';
}
