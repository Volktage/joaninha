<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ClassroomPeriod: string implements HasColor, HasIcon, HasLabel
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => 'Manhã',
            self::Afternoon => 'Tarde',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Morning => 'warning',
            self::Afternoon => 'primary',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Morning => Heroicon::OutlinedSun,
            self::Afternoon => Heroicon::OutlinedCloud,
        };
    }
}
