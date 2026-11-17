<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Pages\SchoolCalendar;
use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListSchoolEvents extends ListRecords
{
    protected static string $resource = SchoolEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Ver calendário')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(SchoolCalendar::getUrl()),
            CreateAction::make()->label('Novo evento'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Próximos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereDate('starts_on', '>=', today())
                    ->orWhereDate('ends_on', '>=', today()))),
            'past' => Tab::make('Já realizados')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('starts_on', '<', today())
                    ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '<', today()))),
            'all' => Tab::make('Todos'),
        ];
    }
}
