<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Etapa de ensino da turma. A Educação Infantil é avaliada por relatórios descritivos, sem nota (LDB, art. 31).
 */
enum EducationStage: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case EarlyChildhood = 'early_childhood';
    case Elementary = 'elementary';

    public function getLabel(): string
    {
        return match ($this) {
            self::EarlyChildhood => 'Educação Infantil',
            self::Elementary => 'Fundamental I',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EarlyChildhood => 'Maternal ao Pré. Avaliação por relatório descritivo, sem nota.',
            self::Elementary => '1º ao 4º ano. Provas e trabalhos com nota de 0 a 10.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EarlyChildhood => 'warning',
            self::Elementary => 'info',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::EarlyChildhood => Heroicon::OutlinedPuzzlePiece,
            self::Elementary => Heroicon::OutlinedBookOpen,
        };
    }

    public function isGraded(): bool
    {
        return $this === self::Elementary;
    }
}
