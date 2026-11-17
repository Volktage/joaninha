<?php

namespace App\Filament\Resources\StaffMembers\RelationManagers;

use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Models\Classroom;
use App\Models\StaffMember;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Turmas em que o(a) professor(a) é regente. A vinculação é feita no cadastro da turma.
 */
class HomeroomClassroomsRelationManager extends RelationManager
{
    protected static string $relationship = 'homeroomClassrooms';

    protected static ?string $title = 'Turmas (regente)';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedAcademicCap;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var StaffMember $ownerRecord */
        return $ownerRecord->isTeacher() && ! $ownerRecord->isSpecialist();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->homeroomClassrooms()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('students'))
            ->description('Para trocar a regente de uma turma, edite a própria turma.')
            ->columns([
                TextColumn::make('name')
                    ->label('Turma')
                    ->weight(FontWeight::Medium)
                    ->icon(fn (Classroom $record) => $record->stage?->getIcon())
                    ->iconColor(fn (Classroom $record) => $record->stage?->getColor()),
                TextColumn::make('period')->label('Período')->badge(),
                TextColumn::make('students_count')->label('Alunos')->icon(Heroicon::OutlinedUsers),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir turma')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Classroom $record): string => ClassroomResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Nenhuma turma como regente');
    }
}
