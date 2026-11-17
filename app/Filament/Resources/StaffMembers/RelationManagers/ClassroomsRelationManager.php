<?php

namespace App\Filament\Resources\StaffMembers\RelationManagers;

use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Models\Classroom;
use App\Models\StaffMember;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Turmas atendidas por um(a) especialista (Inglês, Educação Física, Artes, Música).
 */
class ClassroomsRelationManager extends RelationManager
{
    protected static string $relationship = 'classrooms';

    protected static ?string $title = 'Turmas atendidas';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'Turma';

    protected static ?string $pluralModelLabel = 'Turmas';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var StaffMember $ownerRecord */
        return $ownerRecord->isSpecialist();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->classrooms()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('students'))
            ->columns([
                TextColumn::make('name')
                    ->label('Turma')
                    ->weight(FontWeight::Medium)
                    ->icon(fn (Classroom $record) => $record->stage?->getIcon())
                    ->iconColor(fn (Classroom $record) => $record->stage?->getColor())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')->label('Período')->badge(),
                TextColumn::make('homeroomTeacher.name')->label('Regente')->placeholder('—'),
                TextColumn::make('students_count')->label('Alunos')->icon(Heroicon::OutlinedUsers),
            ])
            ->defaultSort('classrooms.id')
            ->headerActions([
                AttachAction::make()
                    ->label('Vincular turma')
                    ->visible(fn (): bool => auth()->user()->canManageEnrollment())
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir turma')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Classroom $record): string => ClassroomResource::getUrl('edit', ['record' => $record])),
                DetachAction::make()
                    ->label('Desvincular')
                    ->visible(fn (): bool => auth()->user()->canManageEnrollment())
                    ->iconButton()
                    ->color('gray'),
            ])
            ->emptyStateHeading('Nenhuma turma vinculada')
            ->emptyStateDescription('Use "Vincular turma" para indicar as turmas atendidas.');
    }
}
