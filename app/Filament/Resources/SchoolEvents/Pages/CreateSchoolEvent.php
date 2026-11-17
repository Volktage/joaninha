<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Pages\SchoolCalendar;
use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolEvent extends CreateRecord
{
    protected static string $resource = SchoolEventResource::class;

    protected function getRedirectUrl(): string
    {
        return SchoolCalendar::getUrl(['mes' => $this->getRecord()->starts_on->format('Y-m')]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Evento adicionado ao calendário!';
    }
}
