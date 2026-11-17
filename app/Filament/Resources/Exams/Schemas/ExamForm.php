<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Models\Classroom;
use App\Models\StaffMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes da Avaliação')
                    ->description('Ao salvar, o diário de classe é montado automaticamente com todos os alunos da turma.')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->schema(static::fields())
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Campos da avaliação, compartilhados com o cadastro feito a partir da turma.
     *
     * @param  bool|null  $isGraded  Etapa conhecida da turma; `null` decide pela turma selecionada no formulário.
     * @param  Classroom|null  $classroom  Turma fixa (cadastro a partir da turma); `null` usa a turma selecionada.
     * @return array<int, Component>
     */
    public static function fields(bool $includeClassroom = true, ?bool $isGraded = null, ?Classroom $classroom = null): array
    {
        $isGradedClassroom = fn (Get $get): bool => $isGraded
            ?? Classroom::query()->find($get('classroom_id'))?->isGraded()
            ?? true;

        return array_values(array_filter([
            $includeClassroom
                ? Select::make('classroom_id')
                    ->label('Turma')
                    ->relationship('classroom', 'name', modifyQueryUsing: fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, $state) => $set(
                        'teacher_id',
                        auth()->user()->teacherProfile()?->id ?? Classroom::query()->find($state)?->homeroom_teacher_id,
                    ))
                    ->required()
                : null,
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->placeholder('Ex: Avaliação de Matemática – 4º Bimestre'),
            Select::make('teacher_id')
                ->label('Professor(a) responsável')
                ->relationship(
                    'teacher',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->teachingClassroom($classroom?->id ?? $get('classroom_id'))
                        ->when(auth()->user()->teacherProfile(), fn (Builder $query, StaffMember $teacher) => $query->whereKey($teacher->id))
                        ->orderBy('specialty')
                        ->orderBy('name'),
                )
                ->getOptionLabelFromRecordUsing(fn (StaffMember $record): string => "{$record->name} · {$record->specialty?->getLabel()}")
                ->default(fn (): ?int => auth()->user()->teacherProfile()?->id ?? $classroom?->homeroom_teacher_id)
                ->helperText('A regente da turma ou um dos especialistas que a atendem.')
                ->searchable()
                ->preload()
                ->prefixIcon(Heroicon::OutlinedUser),
            DatePicker::make('applied_at')
                ->label('Data de Aplicação')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->closeOnDateSelection()
                ->default(today()),
            TextInput::make('weight')
                ->label('Peso')
                ->numeric()
                ->default(1)
                ->minValue(0.1)
                ->maxValue(10)
                ->step(0.5)
                ->required()
                ->prefixIcon(Heroicon::OutlinedScale)
                ->helperText('Usado na média ponderada. Provas costumam ter peso 2; trabalhos, peso 1.')
                ->visible($isGradedClassroom),
            FileUpload::make('file_path')
                ->label('Arquivo da Prova')
                ->disk('public')
                ->directory('exams')
                ->visibility('public')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(10240)
                ->openable()
                ->downloadable()
                ->nullable()
                ->helperText('Opcional. Anexe a prova em branco ou o gabarito (PDF ou imagem, até 10 MB).')
                ->columnSpanFull(),
        ]));
    }
}
