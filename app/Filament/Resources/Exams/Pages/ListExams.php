<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Enums\ExamStatus;
use App\Filament\Resources\Exams\ExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedSquares2x2),
            ExamStatus::AwaitingGrades->value => Tab::make(ExamStatus::AwaitingGrades->getLabel())
                ->icon(ExamStatus::AwaitingGrades->getIcon())
                ->badge(ExamResource::getEloquentQuery()->awaitingGrades()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingGrades()),
            ExamStatus::Scheduled->value => Tab::make('Agendadas')
                ->icon(ExamStatus::Scheduled->getIcon())
                ->badge(ExamResource::getEloquentQuery()->scheduled()->count() ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->scheduled()),
            ExamStatus::Completed->value => Tab::make('Lançadas')
                ->icon(ExamStatus::Completed->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->gradesCompleted()),
        ];
    }
}
