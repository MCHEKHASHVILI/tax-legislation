<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;
use LogicException;
use Mchekhashvili\TaxLegislation\Contracts\Reverses;
use Mchekhashvili\TaxLegislation\Contracts\TaxableEvent;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;

/**
 * One tax of one country's law: Georgia's VAT, Georgia's income tax, another country's sales tax.
 *
 * Twice a pipeline. In the engine it is one pipe: it adds its outcome to the TaxReport and hands the
 * report on. Inside, it sends its own subject — whatever the country's rules work on — through its
 * stages with Laravel's Pipeline, every stage either answering or calling $next.
 *
 * stages() lists them in order. A stage that takes handlers maps to the handler classes:
 *
 *     Stage\IdentifyOperation::class,
 *     Stage\ApplyExemptions::class => [Exemption\Export::class, Exemption\ExemptCategories::class],
 *
 * Every class is built by the container. An app can replace a tax's stages through
 * config("{configKey()}.pipelines.{name()}").
 */
abstract class Tax
{
    public function __construct(
        protected readonly Container $container,
        protected readonly Config $config,
    ) {}

    /** The key this tax reports under: 'income', 'vat', 'property'. Unique within its country. */
    abstract public function name(): string;

    /** ISO 3166-1 alpha-2 of the country whose law this is. */
    abstract public function country(): string;

    /** ISO 4217 code of the currency the tax is paid in. */
    abstract public function currency(): string;

    /** @return array<int|class-string, class-string|list<class-string>> */
    abstract protected function stages(): array;

    /** What travels through this tax's stages. The report shows what earlier taxes decided. */
    abstract protected function subject(TaxableEvent $event, ?TaxReport $report): object;

    /** The config file the country's package publishes; pipelines are overridden under it. */
    protected function configKey(): string
    {
        return 'tax-legislation';
    }

    public function assess(TaxableEvent $event, ?TaxReport $report = null): Outcome
    {
        if ($event instanceof Reverses) {
            return $this->assess($event->original())->reversed($event->share());
        }

        return new Pipeline($this->container)
            ->send($this->subject($event, $report))
            ->through($this->pipes())
            ->then(fn () => throw new LogicException(static::class . ': no stage answered — the last stage must always answer.'));
    }

    /** As a pipe of the engine. */
    final public function handle(TaxReport $report, Closure $next): TaxReport
    {
        return $next($report->with($this->assess($report->event, $report)));
    }

    /** @return list<object> */
    protected function pipes(): array
    {
        $stages = $this->config->get("{$this->configKey()}.pipelines.{$this->name()}") ?? $this->stages();
        $pipes = [];

        foreach ($stages as $key => $value) {
            $pipes[] = is_int($key)
                ? $this->container->make($value)
                : $this->container->make($key, ['handlers' => array_map($this->container->make(...), $value)]);
        }

        return $pipes;
    }
}
