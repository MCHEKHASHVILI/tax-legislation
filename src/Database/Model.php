<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Database;

use Illuminate\Database\Eloquent\Model as Eloquent;

/** Every tax-legislation table: prefixed and on the connection config('tax-legislation.database') names. */
abstract class Model extends Eloquent
{
    protected $guarded = [];

    /** The table name without the prefix. */
    abstract protected function baseTable(): string;

    public function getTable(): string
    {
        return config('tax-legislation.database.table_prefix', '') . $this->baseTable();
    }

    public function getConnectionName(): ?string
    {
        return config('tax-legislation.database.connection') ?? parent::getConnectionName();
    }
}
