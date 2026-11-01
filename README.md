# tax-legislation

Tax law changes. This package keeps its history, per country, and runs each country's taxes against the law as it stood on the day of the event.

- **Legislation over time.** Legal acts, the taxes a country has had, and the rates, thresholds, exemptions and tables of its law. Each value carries the dates it was in force and the act that set it.
- **A country-neutral engine** that follows the recorded law. Each country's taxes run as Laravel pipelines, with typed outcomes (taxable, levied per unit, exempt, exempt with credit, not taxable, not computed, unresolved). A tax only runs within its period, and an exemption only applies while it was in force. Reports are stored, and events and a queued job are included.
- **Entered through forms, by people now and agents later.** Validated actions keep the law consistent, and a revision log records who changed what, from which source, and why.

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
| `Revision` | One edit to any of these records: who, what, why, from which source |
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

`EloquentRateTable`, `EloquentThresholdTable` and `EloquentTableSource` hand rates, thresholds and tables to the engine as they stood on the event's day. An amendment entered in the database applies from its date with no code change.

## Entering the law

Amendments come in through forms: a person now, an agent later. Each form posts to an action. The action validates like a form request (a `ValidationException` carries field errors back), saves in a transaction, and logs a revision.

| Action | Does | Guards |
|---|---|---|
| `RecordLegalAct` | Records an act | Known country and kind; dates in order; an amended act of the same country |
| `RecordTaxType` | Records a tax for a period | No overlap with another period of the same tax; acts and predecessors of the same country; `engine` is a `Tax` class |
| `AbolishTaxType` | Abolishes a tax from a day | After its start; the act is of its country |
| `AmendLaw` | Changes a rate, threshold, exemption or table row from a day | Every amendment cites its act, of the same country; the day falls in the tax's period; ISO currencies; rate in 0–100% |
| `RepealLaw` | Ends a value from a day | After it began; the act is of its country |

```php
public function store(Request $request, AmendLaw $amend)
{
    $rate = $amend->handle(ValueKind::Rate, $request->all());   // tax_type_id, key, from, legal_act_id, percent, reason, source
    return redirect()->route('rates.show', $rate);
}
```

The rules are public (`$action->rules()`), so a `FormRequest` can reuse them. Authorization belongs to your app: gates and policies on these models.

**Revisions.** Every create, update and delete of a legislation record writes a `Revision`: the record, the old and new values, the editor, the `reason` and the `source` the form gave. This is the editing history, separate from the legal history. Periods record when the law applied a value; revisions record who entered it, when and from what.

```php
$rate->revisions;                       // newest first
$revision->who();                       // 'Nino', or 'agent:matsne-reader'
$revision->changes;                     // ['old' => [...], 'new' => [...]]
```

The editor is the logged-in user. An agent or a job names itself:

```php
app(Editor::class)->as($agentModel, 'Read from Matsne', source: $url)->run(fn () => $amend->handle(...));
app(Editor::class)->as('agent:matsne-reader')->run(...);
```

## Following the law

Where a country's tax types are recorded, the engine reads the law as it stood on the event's day:

- a tax runs only within its period;
- a tax in force that no engine computes (no `engine` class) is reported as `NotComputed`, so it isn't silently missed;
- an exemption answer stands only if the recorded law had that exemption in force. Its conditions stay in code, and articles the law doesn't list are not contradicted;
- rates, thresholds and tables come from `EloquentRateTable`, `EloquentThresholdTable` and `EloquentTableSource`, which a country package binds.

A country with nothing recorded runs as before. `config('tax-legislation.follow_law')` turns all of this off.

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

The engine tests use an invented Armenian sales tax, and the legislation, form and following-the-law tests use invented acts and dates. They exercise the package; they are not legal history.

## License

MIT
