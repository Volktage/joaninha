<?php

namespace App\Filament\Resources\SchoolEvents\Schemas;

use App\Enums\SchoolEventType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SchoolEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evento')
                    ->description('Feriados, recessos e dias pedagógicos da escola toda ficam sem chamada.')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Reunião de pais – 4º bimestre')
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(SchoolEventType::class)
                            ->default(SchoolEventType::Other)
                            ->native(false)
                            ->required(),
                        Select::make('classroom_id')
                            ->label('Turma')
                            ->relationship('classroom', 'name')
                            ->placeholder('Escola toda')
                            ->helperText('Deixe em branco para eventos da escola toda.')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('starts_on')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->live()
                            ->required(),
                        DatePicker::make('ends_on')
                            ->label('Até (opcional)')
                            ->helperText('Para eventos de vários dias, como recessos.')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (Get $get) => $get('starts_on'))
                            ->afterOrEqual('starts_on'),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->autosize()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
