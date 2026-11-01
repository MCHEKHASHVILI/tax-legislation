<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Listeners;

use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\ExemptWithCredit;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Taxable;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Unresolved;
use Mchekhashvili\TaxLegislation\Events\TaxableEventAssessed;
use Mchekhashvili\TaxLegislation\Assessments\Models\Assessment;

/** Keeps every report in the assessments and outcomes tables, when config('tax-legislation.store_reports'). */
final class StoreAssessment
{
    public function handle(TaxableEventAssessed $assessed): void
    {
        $report = $assessed->report;

        $assessment = Assessment::query()->create([
            'event_type' => $report->event::class,
            'payload' => base64_encode(serialize($report->event)),
            'occurred_on' => $report->event->date(),
            'total_tax' => $report->totalTax(),
        ]);

        $assessment->outcomes()->createMany($report->outcomes->map(self::row(...))->values()->all());
    }

    /** @return array<string, mixed> */
    private static function row(Outcome $o): array
    {
        $row = [
            'tax' => $o->tax(),
            'outcome' => match (true) {
                $o instanceof Taxable => 'taxable',
                $o instanceof Levied => 'levied',
                $o instanceof ExemptWithCredit => 'exempt_with_credit',
                $o instanceof Exempt => 'exempt',
                $o instanceof NotTaxable => 'not_taxable',
                $o instanceof Unresolved => 'unresolved',
                default => class_basename($o),
            },
            'article' => $o->basis()->ref,
            'title' => $o->basis()->title ?: null,
            'summary' => (string) $o,
        ];

        return $row + match (true) {
            $o instanceof Taxable => [
                'taxpayer' => $o->taxpayer->id, 'agent' => $o->agent?->id, 'base' => $o->base, 'rate' => $o->rate->percent,
                'amount' => $o->amount, 'collection' => $o->collection->name, 'due_on' => $o->due, 'notes' => $o->notes,
            ],
            $o instanceof Levied => [
                'taxpayer' => $o->taxpayer->id, 'quantity' => $o->quantity, 'unit' => $o->unit,
                'amount' => $o->amount, 'collection' => $o->collection->name, 'due_on' => $o->due, 'notes' => $o->notes,
            ],
            default => [],
        };
    }
}
