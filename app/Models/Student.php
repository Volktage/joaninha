<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Enums\SpecialEducationNeed;
use App\Observers\StudentObserver;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(StudentObserver::class)]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'name',
        'registration_number',
        'birth_date',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_email',
        'allergies',
        'health_notes',
        'special_education',
        'special_education_notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'guardian_relationship' => GuardianRelationship::class,
        'special_education' => SpecialEducationNeed::class,
    ];

    /**
     * Idade em anos completos, ou null quando a data de nascimento não foi informada.
     */
    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * Público da Educação Especial, acompanhado pelo AEE.
     */
    public function isSpecialEducation(): bool
    {
        return $this->special_education !== null;
    }

    public function hasHealthAlert(): bool
    {
        return filled($this->allergies) || filled($this->health_notes);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->seesAllClassrooms()) {
            $query->whereHas('classroom', fn (Builder $query) => $query->visibleTo($user));
        }
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Adiciona `attendance_rate`: percentual de presença (presenças e atrasos) sobre os dias com chamada.
     */
    #[Scope]
    protected function withAttendanceRate(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->selectSub(
            Attendance::query()
                ->whereColumn('attendances.student_id', 'students.id')
                ->selectRaw("ROUND(100 * SUM(status IN ('present', 'late')) / NULLIF(COUNT(*), 0), 1)"),
            'attendance_rate',
        );
    }

    public function attendanceRate(): ?float
    {
        if (array_key_exists('attendance_rate', $this->attributes)) {
            return $this->attributes['attendance_rate'] === null ? null : (float) $this->attributes['attendance_rate'];
        }

        $rate = static::query()->withAttendanceRate()->whereKey($this->getKey())->value('attendance_rate');

        return $rate === null ? null : (float) $rate;
    }

    /**
     * Adiciona a coluna `weighted_average`: média das notas lançadas ponderada pelo peso de cada avaliação.
     */
    #[Scope]
    protected function withWeightedAverage(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->selectSub(
            Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->whereColumn('grades.student_id', 'students.id')
                ->whereNotNull('grades.score')
                ->selectRaw('ROUND(SUM(grades.score * exams.weight) / NULLIF(SUM(exams.weight), 0), 2)'),
            'weighted_average',
        );
    }

    public function weightedAverage(): ?float
    {
        if (array_key_exists('weighted_average', $this->attributes)) {
            return $this->attributes['weighted_average'] === null ? null : (float) $this->attributes['weighted_average'];
        }

        $average = static::query()->withWeightedAverage()->whereKey($this->getKey())->value('weighted_average');

        return $average === null ? null : (float) $average;
    }

    /**
     * Garante uma linha no diário de classe para cada avaliação da turma aplicada de hoje em diante.
     * Avaliações anteriores à chegada do aluno (ex: transferência no meio do ano) não ficam pendentes para ele.
     */
    public function createMissingGradesForClassroomExams(): void
    {
        Exam::query()
            ->where('classroom_id', $this->classroom_id)
            ->whereDate('applied_at', '>=', today())
            ->pluck('id')
            ->each(
                fn (int $examId) => $this->grades()->firstOrCreate(['exam_id' => $examId])
            );
    }
}
