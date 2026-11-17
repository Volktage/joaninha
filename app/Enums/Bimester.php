<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * Bimestres do ano letivo: fevereiro a abril, maio a julho, agosto e setembro, outubro a dezembro.
 */
enum Bimester: int implements HasLabel
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;

    public static function fromDate(CarbonInterface $date): self
    {
        return match (true) {
            $date->month <= 4 => self::First,
            $date->month <= 7 => self::Second,
            $date->month <= 9 => self::Third,
            default => self::Fourth,
        };
    }

    public function getLabel(): string
    {
        return "{$this->value}º bimestre";
    }
}
