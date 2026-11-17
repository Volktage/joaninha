<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AttendanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Justified = 'justified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Presente',
            self::Late => 'Atraso',
            self::Absent => 'Falta',
            self::Justified => 'Falta justificada',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Late => 'A',
            self::Absent => 'F',
            self::Justified => 'FJ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Justified => 'info',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Present => Heroicon::OutlinedCheckCircle,
            self::Late => Heroicon::OutlinedClock,
            self::Absent => Heroicon::OutlinedXCircle,
            self::Justified => Heroicon::OutlinedDocumentText,
        };
    }

    /**
     * Para a frequência legal, atraso conta como presença e falta justificada continua sendo falta.
     */
    public function countsAsPresence(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
