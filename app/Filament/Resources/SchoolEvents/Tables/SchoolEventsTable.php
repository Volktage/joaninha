<?php

namespace App\Filament\Resources\SchoolEvents\Tables;

use App\Enums\SchoolEventType;
use App\Models\SchoolEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchoolEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('classroom'))
            ->columns([
                TextColumn::make('starts_on')
                    ->label('Data')
                    ->formatStateUsing(fn (SchoolEvent $record): string => $record->ends_on
                        ? $record->starts_on->format('d/m').' a '.$record->ends_on->format('d/m/Y')
                        : $record->starts_on->format('d/m/Y'))
                    ->description(fn (SchoolEvent $record): string => ucfirst($record->starts_on->locale('pt_BR')->translatedFormat('l')))
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Evento')
                    ->weight(FontWeight::Medium)
                    ->description(fn (SchoolEvent $record): ?string => $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Escola toda'),
            ])
            ->defaultSort('starts_on')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(SchoolEventType::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('Nenhum evento cadastrado');
    }
}
