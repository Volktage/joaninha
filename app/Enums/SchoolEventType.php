<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum SchoolEventType: string implements HasColor, HasIcon, HasLabel
{
    case Holiday = 'holiday';
    case Recess = 'recess';
    case PedagogicalDay = 'pedagogical_day';
    case ParentsMeeting = 'parents_meeting';
    case ClassCouncil = 'class_council';
    case Celebration = 'celebration';
    case FieldTrip = 'field_trip';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Holiday => 'Feriado',
            self::Recess => 'Recesso',
            self::PedagogicalDay => 'Dia pedagógico (sem aula)',
            self::ParentsMeeting => 'Reunião de pais',
            self::ClassCouncil => 'Conselho de classe',
            self::Celebration => 'Festa e comemoração',
            self::FieldTrip => 'Passeio e estudo do meio',
            self::Other => 'Outro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Holiday => 'danger',
            self::Recess, self::PedagogicalDay => 'gray',
            self::ParentsMeeting => 'info',
            self::ClassCouncil => 'primary',
            self::Celebration => 'warning',
            self::FieldTrip => 'success',
            self::Other => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Holiday => Heroicon::OutlinedFlag,
            self::Recess => Heroicon::OutlinedSun,
            self::PedagogicalDay => Heroicon::OutlinedClipboardDocumentList,
            self::ParentsMeeting => Heroicon::OutlinedUsers,
            self::ClassCouncil => Heroicon::OutlinedPresentationChartLine,
            self::Celebration => Heroicon::OutlinedSparkles,
            self::FieldTrip => Heroicon::OutlinedMap,
            self::Other => Heroicon::OutlinedCalendarDays,
        };
    }

    /**
     * Dias sem aula: não entram na chamada nem no cálculo de frequência.
     */
    public function suspendsClasses(): bool
    {
        return in_array($this, [self::Holiday, self::Recess, self::PedagogicalDay], true);
    }
}
