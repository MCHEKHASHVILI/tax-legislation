<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Facades;

use Illuminate\Support\Facades\Facade;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;

/**
 * @method static \Mchekhashvili\TaxLegislation\Engine\TaxReport process(\Mchekhashvili\TaxLegislation\Contracts\TaxableEvent $event)
 * @method static \Mchekhashvili\TaxLegislation\Engine\TaxReport assess(\Mchekhashvili\TaxLegislation\Contracts\TaxableEvent $event)
 * @method static mixed queue(\Mchekhashvili\TaxLegislation\Contracts\TaxableEvent $event)
 * @method static TaxEngine forCountry(string $alpha2)
 * @method static TaxEngine only(string ...$names)
 * @method static \Mchekhashvili\TaxLegislation\Engine\Tax tax(string $name)
 * @method static \Illuminate\Support\Collection<string, \Mchekhashvili\TaxLegislation\Engine\Tax> taxes()
 * @method static \Mchekhashvili\TaxLegislation\Contracts\Ledger ledger()
 *
 * @see TaxEngine
 */
final class Taxes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TaxEngine::class;
    }
}
