<?php

namespace App\Filament\Resources\Classrooms\Pages;

use App\Filament\Resources\Classrooms\ClassroomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClassroom extends CreateRecord
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Criar'),
            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Criar'),
            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Turma criada com sucesso!';
    }
}
