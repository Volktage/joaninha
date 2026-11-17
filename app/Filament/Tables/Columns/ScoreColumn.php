<?php

namespace App\Filament\Tables\Columns;

use App\Models\Grade;
use Filament\Tables\Columns\TextColumn;

/**
 * Coluna de nota em badge, com as mesmas cores em todo o painel:
 * verde a partir de 7, amarelo de 5 a 6,9 e vermelho abaixo de 5.
 */
class ScoreColumn
{
    public static function make(string $name, string $label = 'Nota'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->badge()
            ->alignCenter()
            ->color(fn ($state): string => Grade::colorForScore(static::normalize($state)))
            ->formatStateUsing(fn ($state): string => Grade::formatScore(static::normalize($state)))
            ->placeholder('—');
    }

    private static function normalize(mixed $state): ?float
    {
        return is_numeric($state) ? (float) $state : null;
    }
}
