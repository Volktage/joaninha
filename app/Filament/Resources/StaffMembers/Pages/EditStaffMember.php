<?php

namespace App\Filament\Resources\StaffMembers\Pages;

use App\Filament\Resources\Concerns\OpensReadOnlyForViewers;
use App\Filament\Resources\StaffMembers\Pages\Concerns\ManagesSystemAccess;
use App\Filament\Resources\StaffMembers\StaffMemberResource;
use App\Models\StaffMember;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

class EditStaffMember extends EditRecord
{
    use ManagesSystemAccess;
    use OpensReadOnlyForViewers;

    protected static string $resource = StaffMemberResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getRelationManagersContentComponent(),
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    public function getHeading(): string
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): string
    {
        /** @var StaffMember $staffMember */
        $staffMember = $this->getRecord();
        $classrooms = $staffMember->isTeacher() ? $staffMember->allClassrooms()->count() : null;

        return collect([
            $staffMember->role?->getLabel(),
            $staffMember->specialty?->getLabel(),
            $classrooms === null ? null : ($classrooms === 1 ? '1 turma' : "{$classrooms} turmas"),
            $staffMember->hired_at ? 'Na escola desde '.$staffMember->hired_at->format('m/Y') : null,
            $staffMember->hasSystemAccess() ? 'Com acesso ao sistema' : 'Sem acesso ao sistema',
        ])->filter()->implode(' · ');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var StaffMember $staffMember */
        $staffMember = $this->getRecord();

        return [...$data, 'system_access' => $staffMember->hasSystemAccess()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractSystemAccess($data);
    }

    protected function afterSave(): void
    {
        $this->applySystemAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('O acesso ao sistema também é removido. Turmas ficam sem esta pessoa vinculada; avaliações e notas são mantidas.'),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->onlyWhenEditable([
            $this->getSaveFormAction()->label('Salvar Alterações'),
            $this->getCancelFormAction()->label('Cancelar'),
        ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Cadastro atualizado!';
    }
}
