<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Http;

use Illuminate\Database\Eloquent\Model;
use Mchekhashvili\TaxLegislation\Drafts\Draft;
use Mchekhashvili\TaxLegislation\Legislation\Models\Exemption;
use Mchekhashvili\TaxLegislation\Legislation\Models\LegalAct;
use Mchekhashvili\TaxLegislation\Legislation\Models\TableEntry;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxRate;
use Mchekhashvili\TaxLegislation\Legislation\Models\TaxType;
use Mchekhashvili\TaxLegislation\Legislation\Models\Threshold;

/**
 * The API's names for the records it points to — 'rate', 'tax-type' — instead of class names. The
 * value kinds keep ValueKind's names, so /values/{kind}/{id} takes the same word.
 *
 * @internal
 */
final class Subjects
{
    /** @var array<string, class-string<Model>> */
    public const array MAP = [
        'legal-act' => LegalAct::class,
        'tax-type' => TaxType::class,
        'rate' => TaxRate::class,
        'threshold' => Threshold::class,
        'exemption' => Exemption::class,
        'table' => TableEntry::class,
        'draft' => Draft::class,
    ];

    /** The API name for a stored morph type; another model keeps its morph type. */
    public static function alias(?string $morphType): ?string
    {
        if ($morphType === null) {
            return null;
        }

        foreach (self::MAP as $alias => $class) {
            if ($morphType === $class || $morphType === new $class()->getMorphClass()) {
                return $alias;
            }
        }

        return $morphType;
    }

    /** The stored morph type for an API name, or null if it names nothing. */
    public static function morphType(string $alias): ?string
    {
        return isset(self::MAP[$alias]) ? new (self::MAP[$alias])()->getMorphClass() : null;
    }

    /** {type, id} for a morph pair, or null. @return ?array{type: string, id: int} */
    public static function ref(?string $type, int|string|null $id): ?array
    {
        return $type === null || $id === null ? null : ['type' => self::alias($type), 'id' => (int) $id];
    }
}
