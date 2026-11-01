<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\Concerns\HasValidity;

/**
 * An exemption over a period — which operations or persons a tax leaves out, and whether the
 * taxpayer keeps its input credit (VAT exempt with credit). Conditions the law attaches go in
 * `conditions`: limits, statuses, document requirements.
 *
 * @property int $id
 * @property int $tax_type_id
 * @property string $key            usually the article: '168.1.ა'
 * @property string $name
 * @property ?string $description
 * @property bool $with_credit
 * @property ?array<string, mixed> $conditions
 * @property ?string $handler       the class that applies it in the engine, if any
 */
final class Exemption extends Model
{
    use HasValidity;

    protected function baseTable(): string
    {
        return 'tax_exemptions';
    }

    protected function casts(): array
    {
        return ['with_credit' => 'boolean', 'conditions' => 'array'];
    }
}
