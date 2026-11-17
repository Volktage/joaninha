<?php

namespace App\Filament\Resources\Classrooms\Tables;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Classrooms\Schemas\ClassroomForm;
use App\Models\Classroom;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Turmas exibidas em cartões: poucas por ano letivo e cada uma com identidade visual da etapa.
 */
class ClassroomsTable
{
    public const string DELETE_WARNING = 'Todos os alunos, avaliações e notas desta turma também serão excluídos. Esta ação não pode ser desfeita.';

    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): ?string => ClassroomResource::canView($record) ? ClassroomResource::getUrl('edit', ['record' => $record]) : null)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('homeroomTeacher')->withCount([
                'students',
                'exams',
                'exams as pending_exams_count' => fn (Builder $query) => $query->awaitingGrades(),
            ]))
            ->contentGrid(['md' => 2, 'lg' => 3, '2xl' => 4])
            ->recordClasses(fn (Classroom $record): string => 'joaninha-classroom-card joaninha-stage-'.$record->stage?->value)
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->label('Turma')
                        ->weight(FontWeight::Bold)
                        ->size(TextSize::Large)
                        ->icon(fn (Classroom $record) => $record->stage?->getIcon())
                        ->iconColor(fn (Classroom $record) => $record->stage?->getColor())
                        ->description(fn (Classroom $record): string => "Ano letivo {$record->year}")
                        ->searchable()
                        ->sortable(),
                    Split::make([
                        TextColumn::make('stage')
                            ->label('Etapa')
                            ->badge()
                            ->grow(false)
                            ->sortable(),
                        TextColumn::make('period')
                            ->label('Período')
                            ->badge()
                            ->grow(false)
                            ->sortable(),
                    ]),
                    TextColumn::make('homeroomTeacher.name')
                        ->label('Regente')
                        ->icon(Heroicon::OutlinedUser)
                        ->formatStateUsing(fn (string $state): string => 'Regente: '.str($state)->explode(' ')->first().' '.str($state)->explode(' ')->last())
                        ->placeholder('Sem regente definida')
                        ->color('gray'),
                    TextColumn::make('students_count')
                        ->label('Alunos')
                        ->icon(Heroicon::OutlinedUsers)
                        ->color('gray')
                        ->formatStateUsing(fn (Classroom $record, int $state): string => ($state === 1 ? '1 aluno' : "{$state} alunos")
                            .' · '.($record->exams_count === 1 ? '1 avaliação' : "{$record->exams_count} avaliações"))
                        ->sortable(),
                    TextColumn::make('pending_exams_count')
                        ->label('Pendências')
                        ->badge()
                        ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success')
                        ->icon(fn (int $state) => $state > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckBadge)
                        ->formatStateUsing(fn (int $state): string => match ($state) {
                            0 => 'Diários em dia',
                            1 => '1 diário pendente',
                            default => "{$state} diários pendentes",
                        }),
                ])->space(3),
            ])
            ->defaultSort('id')
            ->paginated([12, 24, 'all'])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Etapa')
                    ->options(EducationStage::class),
                SelectFilter::make('period')
                    ->label('Período')
                    ->options(ClassroomPeriod::class),
                SelectFilter::make('year')
                    ->label('Ano Letivo')
                    ->options(ClassroomForm::schoolYearOptions())
                    ->default((string) now()->year),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Abrir turma')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->iconPosition(IconPosition::After)
                    ->button()
                    ->outlined()
                    ->size(Size::Small),
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('students')
                            ->label('Ver alunos')
                            ->icon(Heroicon::OutlinedUsers)
                            ->url(fn (Classroom $record): string => ClassroomResource::getUrl('edit', ['record' => $record, 'relation' => '0'])),
                        Action::make('exams')
                            ->label('Ver avaliações')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->url(fn (Classroom $record): string => ClassroomResource::getUrl('edit', ['record' => $record, 'relation' => '1'])),
                    ])->dropdown(false),
                    DeleteAction::make()
                        ->label('Excluir turma')
                        ->modalDescription(self::DELETE_WARNING),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->color('gray')
                    ->tooltip('Mais ações'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(self::DELETE_WARNING),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)
            ->emptyStateHeading('Nenhuma turma encontrada')
            ->emptyStateDescription('Cadastre a primeira turma do ano letivo ou ajuste os filtros.');
    }
}
