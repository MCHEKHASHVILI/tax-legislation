<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Law;

use Attribute;

/**
 * Ties a class to the article of the law it implements, e.g. #[Article('134.1.ე', 'Other Georgian-source income')].
 * Reading the attribute is the only way a rule, exemption or source rule names its legal basis,
 * so the code and the law cannot drift apart silently.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Article
{
    public function __construct(
        public string $ref,
        public string $title = '',
    ) {}
}
