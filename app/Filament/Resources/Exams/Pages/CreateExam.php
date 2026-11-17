<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

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

    /**
     * Após criar, abre direto o diário de classe (já montado pelo ExamObserver) para lançar as notas.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord(), 'relation' => '0']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Avaliação criada! O diário de classe já está pronto.';
    }
}
