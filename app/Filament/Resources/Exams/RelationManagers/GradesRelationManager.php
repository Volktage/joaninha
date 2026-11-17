<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Filament\Tables\Columns\InitialsAvatarColumn;
use App\Filament\Tables\Columns\StudentNameColumn;
use App\Models\Exam;
use App\Models\Grade;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Diário de classe da avaliação: notas de 0 a 10 no Fundamental, relatórios descritivos no Infantil.
 */
class GradesRelationManager extends RelationManager
{
    protected static string $relationship = 'grades';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPencilSquare;

    protected static ?string $modelLabel = 'Lançamento';

    protected static ?string $pluralModelLabel = 'Lançamentos';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        /** @var Exam $ownerRecord */
        return $ownerRecord->classroom->isGraded() ? 'Notas (Diário de Classe)' : 'Relatórios (Diário de Classe)';
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Exam $ownerRecord */
        $blankGrades = $ownerRecord->grades()->blank()->count();

        return $blankGrades > 0 ? "{$blankGrades} pendentes" : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    protected function isGraded(): bool
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();

        return $exam->classroom->isGraded();
    }

    /**
     * Resumo exibido acima do diário: andamento do lançamento e, no Fundamental, a média da turma.
     */
    protected function tableSummary(bool $isGraded): string
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();
        $total = $exam->grades()->count();
        $filled = $total - $exam->grades()->blank()->count();

        if (! $isGraded) {
            return "Relatórios descritivos, sem nota (LDB, art. 31) · {$filled} de {$total} escritos.";
        }

        $average = $exam->grades()->whereNotNull('score')->avg('score');

        return 'Digite a nota (0 a 10) e pressione Tab para salvar · '
            ."{$filled} de {$total} lançados · Média da turma: ".Grade::formatScore($average === null ? null : (float) $average);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_values(array_filter([
                $this->isGraded()
                    ? TextInput::make('score')
                        ->label('Nota')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->step(0.1)
                        ->suffix('/ 10')
                    : null,
                Textarea::make('comments')
                    ->label($this->isGraded() ? 'Observações' : 'Relatório de desenvolvimento')
                    ->rows($this->isGraded() ? 3 : 8)
                    ->autosize()
                    ->placeholder($this->isGraded()
                        ? 'Ex: Faltou – atestado médico apresentado.'
                        : 'Descreva a socialização, a linguagem e a coordenação motora da criança no período.'),
                FileUpload::make('attachments')
                    ->label($this->isGraded() ? 'Provas digitalizadas' : 'Registros (fotos e atividades)')
                    ->disk('local')
                    ->directory('student_exams')
                    ->visibility('private')
                    ->multiple()
                    ->reorderable()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->helperText('Fotos página a página ou PDF (até 10 MB por arquivo).'),
            ])))
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        $isGraded = $this->isGraded();

        return $table
            ->recordTitle(fn (Grade $record): string => $record->student->name)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('student'))
            ->description(fn (): string => $this->tableSummary($isGraded))
            ->columns(array_values(array_filter([
                InitialsAvatarColumn::make('student.name'),
                StudentNameColumn::make('student.name', studentRelationship: 'student')
                    ->searchable()
                    ->sortable(),
                $isGraded
                    ? TextInputColumn::make('score')
                        ->label('Nota')
                        ->type('number')
                        ->step(0.1)
                        ->inputMode('decimal')
                        ->placeholder('—')
                        ->rules(['nullable', 'numeric', 'min:0', 'max:10'])
                        ->extraInputAttributes(fn (Grade $record): array => [
                            'class' => 'joaninha-score joaninha-score-'.Grade::colorForScore($record->score),
                        ])
                        ->disabled(fn (Grade $record): bool => ! auth()->user()->can('update', $record))
                        ->afterStateUpdated(fn () => $this->dispatch('refresh-exam-overview'))
                        ->width('8rem')
                        ->sortable()
                    : null,
                $isGraded
                    ? TextInputColumn::make('comments')
                        ->label('Observações')
                        ->placeholder('Sem observações')
                        ->rules(['nullable', 'string', 'max:1000'])
                        ->disabled(fn (Grade $record): bool => ! auth()->user()->can('update', $record))
                        ->afterStateUpdated(fn () => $this->dispatch('refresh-exam-overview'))
                    : TextColumn::make('comments')
                        ->label('Relatório')
                        ->placeholder('Relatório ainda não escrito')
                        ->wrap()
                        ->lineClamp(3)
                        ->tooltip(fn (Grade $record): ?string => $record->comments),
                IconColumn::make('has_attachments')
                    ->label('Anexos')
                    ->state(fn (Grade $record): bool => filled($record->attachments))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedPaperClip)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(fn (Grade $record): string => filled($record->attachments)
                        ? count($record->attachments).' arquivo(s) anexado(s)'
                        : 'Nenhum arquivo anexado')
                    ->alignCenter(),
            ])))
            ->defaultSort('student.name')
            ->striped()
            ->filters([
                Filter::make('blank')
                    ->label('Somente sem lançamento')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->blank()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label($isGraded ? 'Detalhes' : 'Escrever relatório')
                    ->icon($isGraded ? Heroicon::OutlinedPaperClip : Heroicon::OutlinedPencil)
                    ->modalHeading(fn (Grade $record): string => $record->student->name)
                    ->modalDescription(fn (): string => $this->getOwnerRecord()->title)
                    ->modalWidth($isGraded ? 'lg' : '2xl')
                    ->successNotificationTitle($isGraded ? 'Lançamento salvo' : 'Relatório salvo')
                    ->after(fn () => $this->dispatch('refresh-exam-overview')),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('Nenhum aluno no diário')
            ->emptyStateDescription('Cadastre alunos na turma: eles entram automaticamente no diário desta avaliação.');
    }
}
