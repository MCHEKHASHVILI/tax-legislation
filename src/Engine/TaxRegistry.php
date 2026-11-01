<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Engine;

/**
 * The taxes the engine runs, in order. Each country's package registers its own from its
 * service provider:
 *
 *     $this->app->make(TaxRegistry::class)->register(ImportTax::class, Excise::class, Vat::class);
 *
 * config('tax-legislation.taxes'), when set, replaces the registered list.
 */
final class TaxRegistry
{
    /** @var list<class-string<Tax>> */
    private array $taxes = [];

    /** @param class-string<Tax> ...$taxes */
    public function register(string ...$taxes): self
    {
        foreach ($taxes as $tax) {
            if (!in_array($tax, $this->taxes, true)) {
                $this->taxes[] = $tax;
            }
        }

        return $this;
    }

    /** @param class-string<Tax> ...$taxes */
    public function forget(string ...$taxes): self
    {
        $this->taxes = array_values(array_diff($this->taxes, $taxes));

        return $this;
    }

    /** @return list<class-string<Tax>> */
    public function all(): array
    {
        return $this->taxes;
    }
}
