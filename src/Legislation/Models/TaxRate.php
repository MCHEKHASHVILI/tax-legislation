<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use Mchekhashvili\Money\Laravel\MoneyCast;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\Concerns\HasValidity;
use Mchekhashvili\TaxLegislation\Rate;

/**
 * A tax rate over a period: a percentage (VAT 18%), or an amount per unit (4.50 GEL per cm³,
 * 300 GEL per tonne), or both (cigarettes: 2.75 GEL per 20 plus 20% of the retail price).
 *
 * @property int $id
 * @property int $tax_type_id
 * @property string $key        usually the article: '166', '98.1'
 * @property ?string $name
 * @property ?float $percent
 * @property ?Money $amount
 * @property ?string $unit      'cm³', 't', '20 sticks'
 */
final class TaxRate extends Model
{
    use HasValidity;

    protected function baseTable(): string
    {
        return 'tax_rates';
    }

    protected function casts(): array
    {
        return ['percent' => 'float', 'amount' => MoneyCast::class];
    }

    public function rate(): ?Rate
    {
        return $this->percent === null ? null : Rate::percent($this->percent);
    }
}
