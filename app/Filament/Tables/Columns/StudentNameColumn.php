<?php

namespace App\Filament\Tables\Columns;

use App\Models\Student;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Nome do aluno com a matrícula abaixo e um alerta vermelho quando há alergia ou observação de saúde.
 */
class StudentNameColumn
{
    /**
     * @param  string  $name  Caminho até o nome a partir do registro da linha (ex: "student.name").
     * @param  string|null  $studentRelationship  Relação até o aluno quando a linha não é um Student (ex: "student").
     */
    public static function make(string $name = 'name', ?string $studentRelationship = null): TextColumn
    {
        $student = fn (Model $record): ?Student => $studentRelationship ? $record->{$studentRelationship} : $record;

        return TextColumn::make($name)
            ->label('Aluno')
            ->weight(FontWeight::Medium)
            ->description(fn (Model $record): string => collect([
                'Matrícula '.$student($record)?->registration_number,
                $student($record)?->age() !== null ? $student($record)->age().' anos' : null,
                $student($record)?->special_education ? 'AEE: '.$student($record)->special_education->shortLabel() : null,
            ])->filter()->implode(' · '))
            ->icon(fn (Model $record) => $student($record)?->hasHealthAlert() ? Heroicon::ExclamationTriangle : null)
            ->iconColor('danger')
            ->iconPosition(IconPosition::After)
            ->tooltip(fn (Model $record): ?string => $student($record)?->hasHealthAlert()
                ? collect([
                    $student($record)->allergies ? 'Alergia: '.$student($record)->allergies : null,
                    $student($record)->health_notes,
                ])->filter()->implode(' · ')
                : null);
    }
}
