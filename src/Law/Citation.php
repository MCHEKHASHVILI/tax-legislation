<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Law;

use LogicException;
use ReflectionClass;

final readonly class Citation
{
    public function __construct(
        public string $ref,
        public string $title = '',
    ) {}

    /** Reads the #[Article] attribute from a class or object. */
    public static function of(object|string $subject): self
    {
        $attributes = (new ReflectionClass($subject))->getAttributes(Article::class);

        if ($attributes === []) {
            $name = is_object($subject) ? $subject::class : $subject;
            throw new LogicException("$name has no #[Article] attribute.");
        }

        $article = $attributes[0]->newInstance();

        return new self($article->ref, $article->title);
    }

    public function __toString(): string
    {
        return $this->title === '' ? "Art. {$this->ref}" : "Art. {$this->ref} — {$this->title}";
    }
}
