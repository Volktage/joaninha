<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Pages\SchoolCalendar;
use App\Filament\Resources\Concerns\OpensReadOnlyForViewers;
use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolEvent extends EditRecord
{
    use OpensReadOnlyForViewers;

    protected static string $resource = SchoolEventResource::class;

    public function getHeading(): string
    {
        return $this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successRedirectUrl(SchoolCalendar::getUrl()),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->onlyWhenEditable(parent::getFormActions());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Evento atualizado!';
    }
}
