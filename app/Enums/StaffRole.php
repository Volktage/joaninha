<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Cargo na equipe escolar. Define também o que cada pessoa pode fazer no painel.
 */
enum StaffRole: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Direction = 'direction';
    case ViceDirection = 'vice_direction';
    case Coordination = 'coordination';
    case Secretary = 'secretary';
    case Teacher = 'teacher';
    case Assistant = 'assistant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Direction => 'Direção',
            self::ViceDirection => 'Vice-direção',
            self::Coordination => 'Coordenação Pedagógica',
            self::Secretary => 'Secretaria',
            self::Teacher => 'Professor(a)',
            self::Assistant => 'Auxiliar de classe',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Direction => 'Acesso total, inclusive à gestão da equipe.',
            self::ViceDirection => 'Acesso total, como a Direção, que substitui na ausência.',
            self::Coordination => 'Acompanha o pedagógico: turmas, avaliações, diários e frequência de todas as turmas.',
            self::Secretary => 'Matrículas, turmas, calendário e frequência. Consulta avaliações, mas não lança notas.',
            self::Teacher => 'Vê apenas as próprias turmas: chamada, avaliações e diários de classe.',
            self::Assistant => 'Acompanha as turmas em que atua: consulta alunos (inclusive saúde) e ajuda na chamada.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Direction => 'primary',
            self::ViceDirection => 'primary',
            self::Coordination => 'info',
            self::Secretary => 'warning',
            self::Teacher => 'success',
            self::Assistant => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Direction => Heroicon::OutlinedBuildingLibrary,
            self::ViceDirection => Heroicon::OutlinedBuildingLibrary,
            self::Coordination => Heroicon::OutlinedPresentationChartLine,
            self::Secretary => Heroicon::OutlinedInboxStack,
            self::Teacher => Heroicon::OutlinedAcademicCap,
            self::Assistant => Heroicon::OutlinedHeart,
        };
    }
}
