<?php

declare(strict_types=1);

namespace Mchekhashvili\TaxLegislation\Rates;

use DateTimeImmutable;
use Mchekhashvili\TaxLegislation\Rate;
use OutOfBoundsException;

final readonly class ConfigRateTable implements RateTable
{
    /** @param array<string, array<string, float>> $rates  article => [effective-from date => percent] */
    public function __construct(private array $rates) {}

    public static function fromFile(string $path): self
    {
        return new self(require $path);
    }

    public static function standard(?string $path = null): self
    {
        return self::fromFile($path ?? __DIR__ . '/../../resources/law/rates.php');
    }

    public function rate(string $article, DateTimeImmutable $on): Rate
    {
        $history = $this->rates[$article] ?? throw new OutOfBoundsException("No rate configured for Art. $article.");
        krsort($history);

        foreach ($history as $from => $percent) {
            if (new DateTimeImmutable($from) <= $on) {
                return Rate::percent($percent);
            }
        }

        throw new OutOfBoundsException("Art. $article has no rate in force on {$on->format('Y-m-d')}.");
    }
}
