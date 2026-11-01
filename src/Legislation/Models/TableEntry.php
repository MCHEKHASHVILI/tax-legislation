<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation\Models;

use Illuminate\Database\Eloquent\Builder;
use Mchekhashvili\TaxLegislation\Database\Model;
use Mchekhashvili\TaxLegislation\Legislation\Concerns\HasValidity;

/**
 * One row of a table the law keeps, over a period: an excise rate by HS code, an import tax list
 * entry, a land rate by municipality. `table` names the table ('excise.188.1'), `key` the row
 * ('2710 12'), `values` its columns.
 *
 * @property int $id
 * @property int $tax_type_id
 * @property string $table
 * @property string $key
 * @property array<string, mixed> $values
 */
final class TableEntry extends Model
{
    use HasValidity;

    protected function baseTable(): string
    {
        return 'tax_table_entries';
    }

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    protected static function identifiedBy(): array
    {
        return ['table'];
    }

    /** @param Builder<self> $query */
    public function scopeInTable(Builder $query, string $table): void
    {
        $query->where('table', $table);
    }
}
