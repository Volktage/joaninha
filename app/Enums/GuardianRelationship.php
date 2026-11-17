<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Mother = 'mother';
    case Father = 'father';
    case Grandmother = 'grandmother';
    case Grandfather = 'grandfather';
    case Aunt = 'aunt';
    case Uncle = 'uncle';
    case LegalGuardian = 'legal_guardian';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mother => 'Mãe',
            self::Father => 'Pai',
            self::Grandmother => 'Avó',
            self::Grandfather => 'Avô',
            self::Aunt => 'Tia',
            self::Uncle => 'Tio',
            self::LegalGuardian => 'Responsável legal',
        };
    }
}
