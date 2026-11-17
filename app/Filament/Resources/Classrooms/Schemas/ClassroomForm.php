<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Models\StaffMember;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Turma')
                    ->description('Etapa, ano letivo e período definem como a turma é avaliada e exibida no painel.')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        Grid::make(2)->schema(static::fields()),
                    ])
                    ->columnSpanFull(),
                Section::make('Professores')
                    ->description('A regente conduz a turma e as avaliações do núcleo comum; os especialistas aplicam as de suas áreas.')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->schema([
                        Select::make('homeroom_teacher_id')
                            ->label('Professora regente')
                            ->relationship(
                                'homeroomTeacher',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->teachers()->where('specialty', TeacherSpecialty::Pedagogy),
                            )
                            ->searchable()
                            ->preload()
                            ->prefixIcon(Heroicon::OutlinedUser),
                        Select::make('teachers')
                            ->label('Especialistas')
                            ->relationship(
                                'teachers',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->teachers()->where('specialty', '!=', TeacherSpecialty::Pedagogy),
                            )
                            ->getOptionLabelFromRecordUsing(fn (StaffMember $record): string => "{$record->name} · {$record->specialty?->getLabel()}")
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Select::make('assistants')
                            ->label('Auxiliares de classe')
                            ->helperText('Comuns nas turmas de creche (Maternal), apoiando a regente na rotina.')
                            ->relationship(
                                'assistants',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('role', StaffRole::Assistant),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Campos da turma, reaproveitados no cadastro rápido de turma a partir do formulário de aluno.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome da Turma')
                ->required()
                ->maxLength(255)
                ->placeholder('Ex: Pré II, 3º Ano'),
            Select::make('year')
                ->label('Ano Letivo')
                ->required()
                ->options(static::schoolYearOptions())
                ->default((string) now()->year)
                ->native(false),
            ToggleButtons::make('stage')
                ->label('Etapa de Ensino')
                ->options(EducationStage::class)
                ->default(EducationStage::Elementary)
                ->helperText(fn (Get $get): ?string => ($get('stage') instanceof EducationStage ? $get('stage') : EducationStage::tryFrom((string) $get('stage')))?->getDescription())
                ->live()
                ->inline()
                ->required(),
            ToggleButtons::make('period')
                ->label('Período')
                ->options(ClassroomPeriod::class)
                ->inline()
                ->required(),
        ];
    }

    /**
     * Do próximo ano (para planejar o ano letivo seguinte) até 2020.
     *
     * @return array<string, string>
     */
    public static function schoolYearOptions(): array
    {
        $years = range(now()->year + 1, 2020);

        return array_combine(array_map('strval', $years), array_map('strval', $years));
    }
}
