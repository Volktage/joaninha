<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Tables\Columns\ScoreColumn;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Boletim do aluno: notas ponderadas no Fundamental, relatórios descritivos no Infantil.
 */
class GradesRelationManager extends RelationManager
{
    protected static string $relationship = 'grades';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $modelLabel = 'Lançamento';

    protected static ?string $pluralModelLabel = 'Lançamentos';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        /** @var Student $ownerRecord */
        return $ownerRecord->classroom->isGraded() ? 'Boletim' : 'Relatórios';
    }

    public function table(Table $table): Table
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();
        $isGraded = $student->classroom->isGraded();
        $average = $student->weightedAverage();

        return $table
            ->recordTitle(fn (Grade $record): string => $record->exam->title)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('exam'))
            ->description($isGraded
                ? 'Média ponderada: '.Grade::formatScore($average).'. Notas verdes a partir de 7, amarelas de 5 a 6,9 e vermelhas abaixo de 5.'
                : 'Relatórios descritivos de desenvolvimento, sem nota.')
            ->columns(array_values(array_filter([
                TextColumn::make('exam.title')
                    ->label('Avaliação')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Grade $record): string => $record->exam->applied_at->format('d/m/Y'))
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                $isGraded
                    ? TextColumn::make('exam.weight')
                        ->label('Peso')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state): string => '×'.number_format((float) $state, 1, ',', '.'))
                        ->alignCenter()
                    : null,
                $isGraded ? ScoreColumn::make('score') : null,
                TextColumn::make('comments')
                    ->label($isGraded ? 'Observações' : 'Relatório')
                    ->placeholder($isGraded ? '—' : 'Relatório ainda não escrito')
                    ->wrap()
                    ->lineClamp($isGraded ? 2 : 6),
            ])))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc(
                Exam::query()->select('applied_at')->whereColumn('exams.id', 'grades.exam_id')
            ))
            ->recordActions([
                Action::make('openExam')
                    ->label('Abrir diário')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Grade $record): string => ExamResource::getUrl('edit', ['record' => $record->exam_id, 'relation' => '0'])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentChartBar)
            ->emptyStateHeading('Nenhuma avaliação ainda')
            ->emptyStateDescription('As avaliações da turma aparecem aqui assim que forem cadastradas.');
    }
}
