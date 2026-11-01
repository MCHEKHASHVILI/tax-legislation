<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine\Stage;

use Closure;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Outcome;

/**
 * A stage that asks its handlers in order and returns the first answer — a rule list, an exemption
 * list. If none answers it passes the subject on. The order is the law's order of precedence:
 * the most specific rule first.
 *
 * @template TSubject of object
 * @template THandler of object
 */
abstract class FirstMatch
{
    /** @param list<THandler> $handlers */
    public function __construct(protected readonly array $handlers) {}

    /**
     * @param THandler $handler
     * @param TSubject $subject
     */
    abstract protected function ask(object $handler, object $subject): ?Outcome;

    /**
     * @param TSubject $subject
     * @param Closure(TSubject): Outcome $next
     */
    public function handle(object $subject, Closure $next): Outcome
    {
        foreach ($this->handlers as $handler) {
            if (($outcome = $this->ask($handler, $subject)) !== null) {
                return $outcome;
            }
        }

        return $next($subject);
    }
}
