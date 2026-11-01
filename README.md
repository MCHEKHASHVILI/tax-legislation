# tax-legislation

Tax law changes. This package keeps its history, per country, and runs each country's taxes against the law as it stood on the day of the event.

- **Legislation over time.** Legal acts, the taxes a country has had, and the rates, thresholds, exemptions and tables of its law. Each value carries the dates it was in force and the act that set it.
- **A country-neutral engine.** Each country's taxes run as Laravel pipelines, with typed outcomes (taxable, levied per unit, exempt, exempt with credit, not taxable, unresolved). Reports are stored, and events and a queued job are included.

Country packages build on it: [`mchekhashvili/georgian-tax`](https://github.com/MCHEKHASHVILI/georgian-tax) is Georgia's law. Countries come from [`mchekhashvili/countries`](https://github.com/MCHEKHASHVILI/countries), money from [`mchekhashvili/money`](https://github.com/MCHEKHASHVILI/money).

Requires PHP 8.4 and Laravel 12 or 13.

## Install

Until the packages are on Packagist, add their repositories to your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/tax-legislation" },
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/countries" },
    { "type": "vcs", "url": "https://github.com/MCHEKHASHVILI/money" }
]
```

```
composer require mchekhashvili/money:^1.0@dev mchekhashvili/countries:^1.0@dev mchekhashvili/tax-legislation:dev-main
php artisan migrate
php artisan countries:sync
```

## Legislation

| Model | One row is |
|---|---|
| `LegalAct` | An act that made or changed tax law: a code, an amending law, a resolution, a municipal act. It can amend another act. |
| `TaxType` | One tax in one country for one period: in force from a day, abolished on another, possibly replacing an earlier tax. |
| `TaxRate` | A percentage, an amount per unit, or both. |
| `Threshold` | An amount the law draws a line at, and what it is measured over (a transaction, a calendar year, a rolling 12 months…). |
| `Exemption` | Who or what a tax leaves out, whether input credit is kept, and its conditions. |
| `TableEntry` | One row of a table: an excise rate by HS code, a land rate by municipality. |

Every dated value has `effective_from`, `effective_until` (exclusive), `article`, `legal_act_id` and `repealed_by_id`. An empty `effective_from` means "since before the history recorded here".

Rows are never overwritten. `amend()` closes the value in force and opens the next one, so every past value can still be queried:

```php
$vat = TaxType::query()->ofCountry('GE')->where('code', 'vat')->sole();

TaxRate::amend($vat, '166', ['percent' => 20], from: '2027-01-01', by: $law, article: '166');

$vat->rateOn('166', '2026-06-01');              // 18%
$vat->rateOn('166', '2027-06-01');              // 20%
TaxRate::query()->historyOf($vat, '166')->get(); // every period
$vat->thresholdOn('registration', $day);
$vat->tableOn('excise.188.1', $day);

$exemption->repeal('2027-01-01', by: $law);
$tax->abolish('2008-01-01', by: $law);
$newTax->replaces;                              // the tax it took over from

TaxType::query()->ofCountry('GE')->inForceOn('2007-06-01')->get();
$law->ratesSet; $law->thresholdsSet; $law->taxTypesAbolished;   // everything one act changed
```

`EloquentRateTable` hands rates to the engine as they stood on the event's day, so an amendment entered in the database applies from its date with no code change.

## Engine

A country's package extends `Engine\Tax` for each tax, lists its stages, and registers the taxes:

```php
final class Vat extends Tax
{
    public function name(): string { return 'vat'; }
    public function country(): string { return 'GE'; }
    public function currency(): string { return 'GEL'; }

    protected function stages(): array
    {
        return [
            Stage\IdentifyOperation::class,
            Stage\ApplyExemptions::class => [Exemption\Export::class, Exemption\ExemptCategories::class],
            Stage\ApplyLiability::class => [Rule\ReverseCharge::class, Rule\RegisteredSupplier::class],
        ];
    }

    protected function subject(TaxableEvent $event, ?TaxReport $report): object
    {
        return new Supply($event, ...);
    }
}

// in the country's service provider
$this->callAfterResolving(TaxRegistry::class, fn ($r) => $r->register(ImportTax::class, Excise::class, Vat::class));
```

Each stage has a `handle($subject, $next)` method, like HTTP middleware. `Engine\Stage\FirstMatch` is the stage that asks a list of rules or exemptions and returns the first answer. Stages and handlers are built by the container. An app can replace a tax's stages through `config('<country-config>.pipelines.<tax>')`.

```php
use Mchekhashvili\TaxLegislation\Facades\Taxes;

Taxes::process($event);          // assess, record in the ledger, store the report, fire TaxableEventAssessed
Taxes::assess($event);           // a quote: nothing recorded, nothing fired
Taxes::queue($event);            // on the queue: AssessTaxableEvent
Taxes::forCountry('GE');         // one country's taxes
Taxes::only('vat', 'income');
```

The engine depends on a few contracts, which a country implements:

| Contract | |
|---|---|
| `TaxableEvent` | Anything that happened; the engine only needs its `date()` |
| `Reverses` | An event undoing part of another — every tax reverses its outcome in proportion |
| `Taxpayer` | Whoever pays; an `id` |
| `Ledger` | Records each processed event, for year-to-date thresholds |

`TaxReport` is macroable, so a country adds the questions its law asks: Georgia adds `vatCharged()` and `netToRecipient()`.

## Config

`php artisan vendor:publish --tag=tax-legislation-config` covers the tax list, pipeline overrides, whether reports are stored, the database connection and table prefix, and the queue.

## Tests

```
composer test
```

The engine tests use an invented Armenian sales tax, and the legislation tests use invented acts and dates. They exercise the package; they are not legal history.

## License

MIT
