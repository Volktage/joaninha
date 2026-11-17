<?php

namespace App\Filament\Resources\Exams\Tables;

use App\Enums\EducationStage;
use App\Enums\ExamStatus;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Tables\Columns\ScoreColumn;
use App\Models\Exam;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): ?string => ExamResource::canView($record) ? ExamResource::getUrl('edit', ['record' => $record, 'relation' => '0']) : null)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['classroom', 'teacher'])->withGradingProgress())
            ->columns(static::columns())
            ->defaultSort('applied_at', 'desc')
            ->filters(static::filters())
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Diário')
                    ->icon(Heroicon::OutlinedPencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('As notas e relatórios lançados nestas avaliações também serão excluídos.'),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Nenhuma avaliação encontrada')
            ->emptyStateDescription('Ajuste os filtros ou cadastre uma nova avaliação.');
    }

    /**
     * @param  bool|null  $isGraded  `null` quando a lista mistura turmas do Infantil e do Fundamental.
     * @return array<int, Column>
     */
    public static function columns(bool $showClassroom = true, ?bool $isGraded = null, bool $showTeacher = true): array
    {
        return array_values(array_filter([
            TextColumn::make('title')
                ->label('Avaliação')
                ->weight(FontWeight::Medium)
                ->wrap()
                ->searchable()
                ->sortable(),
            $showClassroom
                ? TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->badge()
                    ->color(fn (Exam $record): string => $record->classroom->stage?->getColor() ?? 'gray')
                    ->icon(fn (Exam $record) => $record->classroom->stage?->getIcon())
                    ->sortable()
                : null,
            $showTeacher
                ? TextColumn::make('teacher.name')
                    ->label('Professor(a)')
                    ->icon(fn (Exam $record) => $record->teacher?->specialty?->getIcon())
                    ->iconColor(fn (Exam $record) => $record->teacher?->specialty?->getColor())
                    ->placeholder('—')
                    ->toggleable()
                : null,
            TextColumn::make('applied_at')
                ->label('Data')
                ->date('d/m/Y')
                ->description(fn (Exam $record): string => $record->appliedAtRelativeLabel())
                ->sortable(),
            $isGraded === false
                ? null
                : TextColumn::make('weight')
                    ->label('Peso')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->formatStateUsing(fn (Exam $record, $state): string => ($isGraded ?? $record->classroom->isGraded())
                        ? '×'.number_format((float) $state, 1, ',', '.')
                        : '—')
                    ->sortable(),
            TextColumn::make('status')
                ->label('Situação')
                ->state(fn (Exam $record): ExamStatus => $record->status())
                ->badge(),
            TextColumn::make('grading_progress')
                ->label('Lançamentos')
                ->state(fn (Exam $record): string => $record->gradingProgressLabel())
                ->icon(Heroicon::OutlinedUsers)
                ->alignCenter(),
            $isGraded === false
                ? null
                : ScoreColumn::make('grades_avg_score', 'Média'),
        ]));
    }

    /**
     * @return array<int, BaseFilter>
     */
    public static function filters(bool $showClassroom = true, bool $showTeacher = true): array
    {
        return array_values(array_filter([
            $showClassroom
                ? SelectFilter::make('classroom')
                    ->label('Turma')
                    ->relationship('classroom', 'name')
                    ->searchable()
                    ->preload()
                : null,
            $showClassroom
                ? SelectFilter::make('stage')
                    ->label('Etapa')
                    ->options(EducationStage::class)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $stage) => $query->whereHas('classroom', fn (Builder $query) => $query->where('stage', $stage)),
                    ))
                : null,
            $showTeacher
                ? SelectFilter::make('teacher')
                    ->label('Professor(a)')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                : null,
            SelectFilter::make('status')
                ->label('Situação')
                ->options(ExamStatus::class)
                ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    ExamStatus::Scheduled->value => $query->scheduled(),
                    ExamStatus::AwaitingGrades->value => $query->awaitingGrades(),
                    ExamStatus::Completed->value => $query->gradesCompleted(),
                    default => $query,
                }),
        ]));
    }
}
