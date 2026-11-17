<?php

namespace App\Filament\Resources\Classrooms\RelationManagers;

use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Tables\Columns\InitialsAvatarColumn;
use App\Filament\Tables\Columns\ScoreColumn;
use App\Filament\Tables\Columns\StudentNameColumn;
use App\Models\Classroom;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Alunos';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Aluno';

    protected static ?string $pluralModelLabel = 'Alunos';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->students()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome Completo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('registration_number')
                    ->label('Número de Matrícula')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->default(fn (): string => StudentForm::suggestRegistrationNumber()),
                DatePicker::make('birth_date')
                    ->label('Data de Nascimento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(today()),
                TextInput::make('guardian_name')
                    ->label('Nome do Responsável')
                    ->maxLength(255),
                TextInput::make('guardian_phone')
                    ->label('Telefone do Responsável')
                    ->tel()
                    ->mask('(99) 99999-9999'),
                TextInput::make('allergies')
                    ->label('Alergias')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var Classroom $classroom */
        $classroom = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withWeightedAverage())
            ->columns([
                InitialsAvatarColumn::make(),
                StudentNameColumn::make()
                    ->searchable(['name', 'registration_number'])
                    ->sortable(),
                ScoreColumn::make('weighted_average', 'Média ponderada')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('weighted_average', $direction))
                    ->visible($classroom->isGraded()),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedUserPlus),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Ficha')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->url(fn (Student $record): string => StudentResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make()
                    ->iconButton()
                    ->color('gray')
                    ->modalDescription('As notas e relatórios deste aluno também serão excluídos.'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('Nenhum aluno nesta turma')
            ->emptyStateDescription('Cadastre os alunos para que eles apareçam no diário de classe das avaliações.');
    }
}
