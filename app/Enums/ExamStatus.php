<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Situação do lançamento de uma avaliação, derivada da data de aplicação e do preenchimento do diário.
 */
enum ExamStatus: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'scheduled';
    case AwaitingGrades = 'awaiting_grades';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::AwaitingGrades => 'Lançamento pendente',
            self::Completed => 'Lançada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::AwaitingGrades => 'warning',
            self::Completed => 'success',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Scheduled => Heroicon::OutlinedCalendarDays,
            self::AwaitingGrades => Heroicon::OutlinedClock,
            self::Completed => Heroicon::OutlinedCheckBadge,
        };
    }
}
