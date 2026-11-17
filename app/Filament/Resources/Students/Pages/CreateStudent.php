<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

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
        return 'Aluno cadastrado com sucesso!';
    }
}
