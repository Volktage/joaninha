<?php

namespace App\Filament\Resources\StaffMembers\Pages;

use App\Filament\Resources\StaffMembers\Pages\Concerns\ManagesSystemAccess;
use App\Filament\Resources\StaffMembers\StaffMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffMember extends CreateRecord
{
    use ManagesSystemAccess;

    protected static string $resource = StaffMemberResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractSystemAccess($data);
    }

    protected function afterCreate(): void
    {
        $this->applySystemAccess();
    }

    public function getTitle(): string
    {
        return 'Adicionar à equipe';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pessoa cadastrada na equipe!';
    }
}
