<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Legislation;

/** What kind of legal act changed the law. */
enum ActKind: string
{
    case Constitution = 'constitution';
    case Code = 'code';                 // a codified law: the Tax Code
    case Law = 'law';                   // an act of parliament, often amending a code
    case Resolution = 'resolution';     // a government resolution
    case Order = 'order';               // a minister's order
    case Decree = 'decree';
    case MunicipalAct = 'municipal';    // a municipal council's normative act — local taxes
    case Treaty = 'treaty';
    case CourtDecision = 'court';       // e.g. a constitutional court striking a provision down
    case Other = 'other';
}
