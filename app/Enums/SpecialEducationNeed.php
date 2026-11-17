<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Público da Educação Especial atendido pelo AEE (Atendimento Educacional Especializado).
 */
enum SpecialEducationNeed: string implements HasLabel
{
    case Autism = 'autism';
    case IntellectualDisability = 'intellectual';
    case PhysicalDisability = 'physical';
    case HearingImpairment = 'hearing';
    case VisualImpairment = 'visual';
    case Giftedness = 'gifted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Autism => 'TEA (Transtorno do Espectro Autista)',
            self::IntellectualDisability => 'Deficiência intelectual',
            self::PhysicalDisability => 'Deficiência física',
            self::HearingImpairment => 'Deficiência auditiva',
            self::VisualImpairment => 'Deficiência visual (baixa visão)',
            self::Giftedness => 'Altas habilidades/superdotação',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Autism => 'TEA',
            self::IntellectualDisability => 'Def. intelectual',
            self::PhysicalDisability => 'Def. física',
            self::HearingImpairment => 'Def. auditiva',
            self::VisualImpairment => 'Def. visual',
            self::Giftedness => 'Altas habilidades',
        };
    }
}
