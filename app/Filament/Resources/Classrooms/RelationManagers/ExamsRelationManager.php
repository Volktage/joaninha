<?php

namespace App\Filament\Resources\Classrooms\RelationManagers;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Schemas\ExamForm;
use App\Filament\Resources\Exams\Tables\ExamsTable;
use App\Models\Classroom;
use App\Models\Exam;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    protected static ?string $title = 'Avaliações';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Avaliação';

    protected static ?string $pluralModelLabel = 'Avaliações';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->exams()->count();
    }

    public function form(Schema $schema): Schema
    {
        /** @var Classroom $classroom */
        $classroom = $this->getOwnerRecord();

        return $schema
            ->components(ExamForm::fields(includeClassroom: false, isGraded: $classroom->isGraded(), classroom: $classroom))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var Classroom $classroom */
        $classroom = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('teacher')->withGradingProgress())
            ->columns(ExamsTable::columns(showClassroom: false, isGraded: $classroom->isGraded()))
            ->defaultSort('applied_at', 'desc')
            ->filters(ExamsTable::filters(showClassroom: false))
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Diário')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Exam $record): string => ExamResource::getUrl('edit', ['record' => $record, 'relation' => '0'])),
                DeleteAction::make()
                    ->iconButton()
                    ->color('gray')
                    ->modalDescription('As notas e relatórios lançados nesta avaliação também serão excluídos.'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Nenhuma avaliação cadastrada')
            ->emptyStateDescription('Crie uma avaliação e o diário de classe é montado automaticamente com os alunos da turma.');
    }
}
