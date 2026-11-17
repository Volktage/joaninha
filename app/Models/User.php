<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Sem este contrato o Filament só libera o painel com APP_ENV=local, bloqueando todos em produção.
     * O painel não tem cadastro público: apenas usuários criados pela escola conseguem entrar.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    /**
     * Cadastro na equipe escolar. Usuários sem cadastro são administradores do sistema (acesso total).
     */
    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class);
    }

    public function role(): ?StaffRole
    {
        return $this->staffMember?->role;
    }

    public function roleLabel(): string
    {
        return $this->role()?->getLabel() ?? 'Administração do sistema';
    }

    public function isAdministrator(): bool
    {
        return $this->staffMember === null;
    }

    public function hasRole(StaffRole ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    /**
     * Administração do sistema e Direção podem tudo, inclusive gerir a equipe e dar acessos.
     */
    public function hasFullAccess(): bool
    {
        return $this->isAdministrator() || $this->hasRole(StaffRole::Direction, StaffRole::ViceDirection);
    }

    public function canManageStaff(): bool
    {
        return $this->hasFullAccess();
    }

    /**
     * Turmas e matrículas: Direção, Coordenação e Secretaria.
     */
    public function canManageEnrollment(): bool
    {
        return $this->hasFullAccess() || $this->hasRole(StaffRole::Coordination, StaffRole::Secretary);
    }

    /**
     * Avaliações e diários de qualquer turma: Direção e Coordenação.
     */
    public function canManageAllAssessments(): bool
    {
        return $this->hasFullAccess() || $this->hasRole(StaffRole::Coordination);
    }

    public function canManageCalendar(): bool
    {
        return $this->canManageEnrollment();
    }

    /**
     * Professores enxergam apenas as próprias turmas; os demais cargos enxergam a escola inteira.
     */
    public function seesAllClassrooms(): bool
    {
        return ! $this->hasRole(StaffRole::Teacher, StaffRole::Assistant);
    }

    /**
     * Cadastro de professor(a) do usuário, quando o cargo é de docente.
     */
    public function teacherProfile(): ?StaffMember
    {
        return $this->hasRole(StaffRole::Teacher) ? $this->staffMember : null;
    }

    /**
     * Cadastro de quem atua dentro das turmas (professores e auxiliares de classe), para limitar o que enxerga.
     */
    public function classroomStaffProfile(): ?StaffMember
    {
        return $this->hasRole(StaffRole::Teacher, StaffRole::Assistant) ? $this->staffMember : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
