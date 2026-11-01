<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use Mchekhashvili\Money\Laravel\MoneyCast;
use Mchekhashvili\Money\Money;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\Concerns\HasValidity;
use Mchekhashvili\TaxLegislation\Legislation\ThresholdPeriod;

/**
 * An amount the law draws a line at, over a period: 100,000 GEL of 12-month turnover for VAT
 * registration, 500,000 GEL a year for small business status.
 *
 * @property int $id
 * @property int $tax_type_id
 * @property string $key
 * @property ?string $name
 * @property Money $amount
 * @property ?ThresholdPeriod $period   what the amount is measured over
 */
final class Threshold extends Model
{
    use HasValidity;

    protected function baseTable(): string
    {
        return 'tax_thresholds';
    }

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class, 'period' => ThresholdPeriod::class];
    }
}
