<?php

namespace App\Models;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'stage',
        'year',
        'period',
        'homeroom_teacher_id',
    ];

    protected $casts = [
        'stage' => EducationStage::class,
        'period' => ClassroomPeriod::class,
    ];

    /**
     * Professora regente: responsável pela turma e pelas avaliações das disciplinas do núcleo comum.
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'homeroom_teacher_id');
    }

    /**
     * Professores especialistas que atendem a turma.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'classroom_teacher', 'classroom_id', 'teacher_id')->withTimestamps();
    }

    /**
     * Auxiliares de classe que acompanham a turma.
     */
    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'classroom_assistant', 'classroom_id', 'staff_member_id')->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Indica se a turma recebe notas de 0 a 10 (Fundamental) ou apenas relatórios descritivos (Infantil).
     */
    /**
     * Professores veem só as turmas em que são regentes ou especialistas; os demais cargos veem todas.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllClassrooms()) {
            return;
        }

        $staffMemberId = $user->classroomStaffProfile()?->id;

        $query->where(function (Builder $query) use ($staffMemberId) {
            $query->where('homeroom_teacher_id', $staffMemberId)
                ->orWhereHas('teachers', fn (Builder $query) => $query->whereKey($staffMemberId))
                ->orWhereHas('assistants', fn (Builder $query) => $query->whereKey($staffMemberId));
        });
    }

    public function isTaughtBy(?StaffMember $teacher): bool
    {
        if ($teacher === null) {
            return false;
        }

        return $this->homeroom_teacher_id === $teacher->id
            || $this->teachers()->whereKey($teacher->id)->exists()
            || $this->assistants()->whereKey($teacher->id)->exists();
    }

    public function isGraded(): bool
    {
        return $this->stage?->isGraded() ?? true;
    }
}
