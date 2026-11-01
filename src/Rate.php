<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation;

use InvalidArgumentException;
use Stringable;

/** A tax rate, held as the percentage the Code writes — 18, 15, 0.6 — so money math stays exact. */
final readonly class Rate implements Stringable
{
    /** The rate as a fraction, 0.18 for 18%. For display; amounts use the percent. */
    public float $value;

    private function __construct(public float $percent)
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("Rate must be between 0% and 100%, got $percent%.");
        }

        $this->value = $percent / 100;
    }

    public static function percent(float $percent): self
    {
        return new self($percent);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->percent, 2, '.', ''), '0'), '.') . '%';
    }
}
