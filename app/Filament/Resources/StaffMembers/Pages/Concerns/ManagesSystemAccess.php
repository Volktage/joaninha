<?php

namespace App\Filament\Resources\StaffMembers\Pages\Concerns;

use App\Enums\StaffRole;
use App\Filament\Resources\StaffMembers\Schemas\StaffMemberForm;
use App\Models\StaffMember;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

/**
 * Separa os campos de acesso (que não pertencem ao cadastro) e cria, atualiza ou remove o login
 * depois que o membro da equipe é salvo.
 *
 * @mixin CreateRecord|EditRecord
 */
trait ManagesSystemAccess
{
    protected ?bool $grantSystemAccess = null;

    protected ?string $systemAccessPassword = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractSystemAccess(array $data): array
    {
        if (array_key_exists('system_access', $data)) {
            $this->grantSystemAccess = (bool) $data['system_access'];
            $this->systemAccessPassword = filled($data['password'] ?? null) ? $data['password'] : null;
        }

        unset($data['system_access'], $data['password']);

        if (StaffMemberForm::role($data['role'] ?? null) !== StaffRole::Teacher) {
            $data['specialty'] = null;
        }

        return $data;
    }

    protected function applySystemAccess(): void
    {
        if ($this->grantSystemAccess === null) {
            return;
        }

        /** @var StaffMember $staffMember */
        $staffMember = $this->getRecord();
        $staffMember->syncSystemAccess($this->grantSystemAccess, $this->systemAccessPassword);
    }
}
