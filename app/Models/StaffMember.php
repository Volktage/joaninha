<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Membro da equipe escolar: direção, coordenação, secretaria ou professor(a).
 * Pode ter um login (User), cujas permissões no painel seguem o cargo.
 */
class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'specialty',
        'hired_at',
    ];

    protected $casts = [
        'role' => StaffRole::class,
        'specialty' => TeacherSpecialty::class,
        'hired_at' => 'date',
    ];

    /**
     * Ao sair da equipe, o login também é removido. Um usuário sem cadastro na equipe seria tratado
     * como administrador do sistema, então ele não pode sobrar.
     */
    protected static function booted(): void
    {
        static::deleting(function (StaffMember $staffMember): void {
            $staffMember->user?->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Turmas em que é a professora regente.
     */
    public function homeroomClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'homeroom_teacher_id');
    }

    /**
     * Turmas atendidas como especialista (Inglês, Educação Física, Artes, Música).
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teacher', 'teacher_id', 'classroom_id')->withTimestamps();
    }

    /**
     * Turmas acompanhadas como auxiliar de classe.
     */
    public function assistedClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_assistant', 'staff_member_id', 'classroom_id')->withTimestamps();
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'teacher_id');
    }

    public function isTeacher(): bool
    {
        return $this->role === StaffRole::Teacher;
    }

    public function isAssistant(): bool
    {
        return $this->role === StaffRole::Assistant;
    }

    public function isSpecialist(): bool
    {
        return $this->isTeacher() && (bool) $this->specialty?->isSpecialist();
    }

    public function hasSystemAccess(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Todas as turmas da pessoa: como regente, especialista ou auxiliar de classe.
     *
     * @return Collection<int, Classroom>
     */
    public function allClassrooms(): Collection
    {
        return $this->homeroomClassrooms
            ->merge($this->classrooms)
            ->merge($this->assistedClassrooms)
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    /**
     * Cria, atualiza ou remove o login do membro da equipe. O e-mail do login acompanha o do cadastro.
     */
    public function syncSystemAccess(bool $enabled, ?string $password = null): void
    {
        if (! $enabled) {
            $this->user?->delete();
            $this->user()->dissociate()->save();

            return;
        }

        $user = $this->user ?? new User;
        $user->forceFill(array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'password' => filled($password) ? $password : null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]))->save();

        $this->user()->associate($user)->save();
    }

    #[Scope]
    protected function teachers(Builder $query): void
    {
        $query->where('role', StaffRole::Teacher);
    }

    /**
     * Professores que podem aplicar avaliações na turma: a regente e os especialistas vinculados.
     */
    #[Scope]
    protected function teachingClassroom(Builder $query, ?int $classroomId): void
    {
        $query->where(function (Builder $query) use ($classroomId) {
            $query->whereHas('homeroomClassrooms', fn (Builder $query) => $query->whereKey($classroomId))
                ->orWhereHas('classrooms', fn (Builder $query) => $query->whereKey($classroomId));
        });
    }
}
