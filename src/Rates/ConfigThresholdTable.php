<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\Money\Currency;
use Mchekhashvili\Money\Money;
use OutOfBoundsException;

/**
 * Thresholds from a PHP array: tax => key => [article, [effective-from => amount]].
 * An empty effective-from ('') means "since before the recorded history".
 */
final readonly class ConfigThresholdTable implements ThresholdTable
{
    /** @param array<string, array<string, array{0: string, 1: array<string, int|float|string>}>> $thresholds */
    public function __construct(private array $thresholds, private Currency|string $currency) {}

    public static function fromFile(string $path, Currency|string $currency): self
    {
        return new self(require $path, $currency);
    }

    public function threshold(string $tax, string $key, DateTimeImmutable $on): Money
    {
        [, $history] = $this->thresholds[$tax][$key] ?? throw new OutOfBoundsException("No $tax threshold '$key' configured.");
        krsort($history);

        foreach ($history as $from => $amount) {
            if ($from === '' || new DateTimeImmutable((string) $from) <= $on) {
                return Money::of($amount, $this->currency);
            }
        }

        throw new OutOfBoundsException("The $tax threshold '$key' was not in force on {$on->format('Y-m-d')}.");
    }
}
