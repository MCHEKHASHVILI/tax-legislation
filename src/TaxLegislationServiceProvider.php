<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Mchekhashvili\TaxLegislation\Contracts\Ledger;
use Mchekhashvili\TaxLegislation\Engine\Tax;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use Mchekhashvili\TaxLegislation\Engine\TaxRegistry;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;
use Mchekhashvili\TaxLegislation\Ledger\NullLedger;
use Mchekhashvili\TaxLegislation\Legislation\Law;
use Mchekhashvili\TaxLegislation\Listeners\StoreAssessment;
use Mchekhashvili\TaxLegislation\Rates\CountryTables;
use Mchekhashvili\TaxLegislation\Rates\RateTable;
use Mchekhashvili\TaxLegislation\Rates\TableSource;
use Mchekhashvili\TaxLegislation\Rates\ThresholdTable;
use Mchekhashvili\TaxLegislation\Revisions\Editor;

final class TaxLegislationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tax-legislation.php', 'tax-legislation');

        $this->app->singleton(TaxRegistry::class);

        // Rates, thresholds and tables per country; a type-hint resolves to the assessing tax's country.
        $this->app->singleton(CountryTables::class);
        foreach ([RateTable::class, ThresholdTable::class, TableSource::class] as $contract) {
            $this->app->bindIf($contract, fn (Container $app) => $app->make(CountryTables::class)->forCurrent($contract));
        }

        $this->app->scoped(Law::class);
        $this->app->scoped(Editor::class);
        $this->app->bindIf(Ledger::class, NullLedger::class, shared: true);

        $this->app->scoped(TaxEngine::class, fn (Container $app) => new TaxEngine(
            $app,
            $app->make(Ledger::class),
            $app->make('events'),
            $app->make(Bus::class),
            array_map(
                fn (string $tax): Tax => $app->make($tax),
                config('tax-legislation.taxes') ?? $app->make(TaxRegistry::class)->all(),
            ),
        ));
    }

    public function boot(Dispatcher $events): void
    {
        if (config('tax-legislation.store_reports')) {
            $events->listen(TaxableEventAssessed::class, StoreAssessment::class);
        }

        if (config('tax-legislation.database.run_migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/tax-legislation.php' => config_path('tax-legislation.php')], 'tax-legislation-config');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'tax-legislation-migrations');
        }
    }
}
