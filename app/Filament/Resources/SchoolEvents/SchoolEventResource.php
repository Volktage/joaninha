<?php

namespace App\Filament\Resources\SchoolEvents;

use App\Filament\Resources\SchoolEvents\Pages\CreateSchoolEvent;
use App\Filament\Resources\SchoolEvents\Pages\EditSchoolEvent;
use App\Filament\Resources\SchoolEvents\Pages\ListSchoolEvents;
use App\Filament\Resources\SchoolEvents\Schemas\SchoolEventForm;
use App\Filament\Resources\SchoolEvents\Tables\SchoolEventsTable;
use App\Models\SchoolEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Eventos do calendário escolar. No menu aparece a página de Calendário; esta lista fica acessível a partir dela.
 */
class SchoolEventResource extends Resource
{
    protected static ?string $model = SchoolEvent::class;

    protected static ?string $slug = 'events';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventos do calendário';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return SchoolEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolEvents::route('/'),
            'create' => CreateSchoolEvent::route('/create'),
            'edit' => EditSchoolEvent::route('/{record}/edit'),
        ];
    }
}
