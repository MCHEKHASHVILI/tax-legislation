<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Tests;

use Mchekhashvili\Countries\CountriesServiceProvider;
use Mchekhashvili\TaxLegislation\TaxLegislationServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [CountriesServiceProvider::class, TaxLegislationServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Taxes' => \Mchekhashvili\TaxLegislation\Facades\Taxes::class];
    }
}
