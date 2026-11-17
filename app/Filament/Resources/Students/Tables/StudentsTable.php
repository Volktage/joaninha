<?php

namespace App\Filament\Resources\Students\Tables;

use App\Enums\EducationStage;
use App\Enums\SpecialEducationNeed;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Tables\Columns\InitialsAvatarColumn;
use App\Filament\Tables\Columns\ScoreColumn;
use App\Filament\Tables\Columns\StudentNameColumn;
use App\Models\Attendance;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): ?string => StudentResource::canView($record) ? StudentResource::getUrl('edit', ['record' => $record]) : null)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('classroom')->withWeightedAverage()->withAttendanceRate())
            ->columns([
                InitialsAvatarColumn::make(),
                StudentNameColumn::make()
                    ->searchable(['name', 'registration_number', 'guardian_name'])
                    ->sortable(),
                TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->badge()
                    ->color(fn (Student $record): string => $record->classroom->stage?->getColor() ?? 'gray')
                    ->icon(fn (Student $record) => $record->classroom->stage?->getIcon())
                    ->sortable(),
                ScoreColumn::make('weighted_average', 'Média ponderada')
                    ->tooltip('Média das notas lançadas, ponderada pelo peso de cada avaliação')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('weighted_average', $direction)),
                TextColumn::make('attendance_rate')
                    ->label('Frequência')
                    ->badge()
                    ->color(fn ($state): string => Attendance::colorForRate(is_numeric($state) ? (float) $state : null))
                    ->formatStateUsing(fn ($state): string => Attendance::formatRate(is_numeric($state) ? (float) $state : null))
                    ->placeholder('—')
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('attendance_rate', $direction)),
                TextColumn::make('guardian_name')
                    ->label('Responsável')
                    ->description(fn (Student $record): ?string => $record->guardian_phone)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('low_attendance')
                    ->label('Somente frequência abaixo de 85%')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('students.id', Attendance::query()
                        ->select('student_id')
                        ->groupBy('student_id')
                        ->havingRaw("100 * SUM(status IN ('present', 'late')) / COUNT(*) < ?", [Attendance::WARNING_RATE]))),
                SelectFilter::make('special_education')
                    ->label('Educação Especial (AEE)')
                    ->options(SpecialEducationNeed::class),
                Filter::make('health_alert')
                    ->label('Somente com alerta de saúde')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNotNull('allergies')->orWhereNotNull('health_notes'))),
                SelectFilter::make('classroom')
                    ->label('Turma')
                    ->relationship('classroom', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('stage')
                    ->label('Etapa')
                    ->options(EducationStage::class)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $stage) => $query->whereHas('classroom', fn (Builder $query) => $query->where('stage', $stage)),
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Ficha')
                    ->icon(Heroicon::OutlinedIdentification),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('As notas e relatórios destes alunos também serão excluídos.'),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('Nenhum aluno encontrado')
            ->emptyStateDescription('Ajuste a busca e os filtros ou cadastre um novo aluno.');
    }
}
