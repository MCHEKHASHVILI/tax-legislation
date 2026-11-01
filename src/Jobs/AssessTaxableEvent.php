<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Jobs;

use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\TaxEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Assesses an event on the queue: Taxes::queue($event). Listen for TaxableEventAssessed
 * to act on the result.
 *
 * Events for the same party should be assessed in order, because each one reads the totals the
 * earlier ones left in the ledger. Use a queue with a single worker, or chain the jobs.
 */
final class AssessTaxableEvent implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly TaxableEvent $event)
    {
        $this->onConnection(config('tax-legislation.queue.connection'));
        $this->onQueue(config('tax-legislation.queue.queue'));
    }

    public function handle(TaxEngine $engine): void
    {
        $engine->process($this->event);
    }
}
