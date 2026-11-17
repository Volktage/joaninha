<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\GuardianRelationship;
use App\Enums\SpecialEducationNeed;
use App\Filament\Resources\Classrooms\Schemas\ClassroomForm;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Aluno')
                    ->description('Ao definir ou trocar a turma, o aluno entra automaticamente no diário das avaliações dela.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('registration_number')
                            ->label('Número de Matrícula')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->default(fn (): string => static::suggestRegistrationNumber())
                            ->helperText('Formato: ano de entrada + sequência. Um número livre já vem sugerido.'),
                        DatePicker::make('birth_date')
                            ->label('Data de Nascimento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(today())
                            ->minDate(today()->subYears(15))
                            ->prefixIcon(Heroicon::OutlinedCake),
                        Select::make('classroom_id')
                            ->label('Turma')
                            ->relationship('classroom', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Grid::make(2)->schema(ClassroomForm::fields()),
                            ])
                            ->createOptionModalHeading('Nova turma')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Responsável')
                    ->description('Contato usado pela escola para avisos, reuniões e emergências.')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextInput::make('guardian_name')
                            ->label('Nome do Responsável')
                            ->maxLength(255),
                        Select::make('guardian_relationship')
                            ->label('Parentesco')
                            ->options(GuardianRelationship::class)
                            ->native(false),
                        TextInput::make('guardian_phone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->placeholder('(11) 99999-9999')
                            ->prefixIcon(Heroicon::OutlinedPhone),
                        TextInput::make('guardian_email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedEnvelope),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
                Section::make('Educação Especial (AEE)')
                    ->description('Para alunos público da Educação Especial, acompanhados pela professora de AEE.')
                    ->icon(Heroicon::OutlinedLifebuoy)
                    ->schema([
                        Select::make('special_education')
                            ->label('Público da Educação Especial')
                            ->options(SpecialEducationNeed::class)
                            ->placeholder('Não se aplica')
                            ->native(false)
                            ->live(),
                        Textarea::make('special_education_notes')
                            ->label('Adaptações e acompanhamento')
                            ->placeholder('Ex: Avaliações adaptadas, apoio na rotina, uso de comunicação alternativa.')
                            ->rows(2)
                            ->autosize()
                            ->visible(fn (Get $get): bool => filled($get('special_education'))),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
                Section::make('Saúde')
                    ->description('Informações exibidas em destaque na ficha do aluno.')
                    ->icon(Heroicon::OutlinedHeart)
                    ->schema([
                        TextInput::make('allergies')
                            ->label('Alergias')
                            ->placeholder('Ex: Lactose, amendoim')
                            ->maxLength(255),
                        Textarea::make('health_notes')
                            ->label('Observações de saúde')
                            ->placeholder('Ex: Asma leve, usa bombinha quando necessário.')
                            ->rows(2)
                            ->autosize(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Próximo número de matrícula livre: ano atual + sequência seguinte à maior já usada (ex: 20260137).
     */
    public static function suggestRegistrationNumber(): string
    {
        $highestSequence = Student::query()
            ->pluck('registration_number')
            ->filter(fn (string $number): bool => preg_match('/^\d{8}$/', $number) === 1)
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        return sprintf('%d%04d', now()->year, $highestSequence + 1);
    }
}
