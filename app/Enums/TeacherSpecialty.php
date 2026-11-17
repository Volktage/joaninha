<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TeacherSpecialty: string implements HasColor, HasIcon, HasLabel
{
    case Pedagogy = 'pedagogy';
    case English = 'english';
    case PhysicalEducation = 'physical_education';
    case Arts = 'arts';
    case Music = 'music';
    case SpecialEducation = 'special_education';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pedagogy => 'Pedagogia (regente)',
            self::English => 'Inglês',
            self::PhysicalEducation => 'Educação Física',
            self::Arts => 'Artes',
            self::Music => 'Música',
            self::SpecialEducation => 'AEE (Educação Especial)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pedagogy => 'primary',
            self::English => 'info',
            self::PhysicalEducation => 'success',
            self::Arts => 'warning',
            self::Music => 'gray',
            self::SpecialEducation => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Pedagogy => Heroicon::OutlinedAcademicCap,
            self::English => Heroicon::OutlinedLanguage,
            self::PhysicalEducation => Heroicon::OutlinedTrophy,
            self::Arts => Heroicon::OutlinedPaintBrush,
            self::Music => Heroicon::OutlinedMusicalNote,
            self::SpecialEducation => Heroicon::OutlinedLifebuoy,
        };
    }

    /**
     * Especialistas atendem várias turmas; a regente é vinculada diretamente à turma.
     */
    public function isSpecialist(): bool
    {
        return $this !== self::Pedagogy;
    }
}
