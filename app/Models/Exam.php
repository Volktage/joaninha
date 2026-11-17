<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Observers\ExamObserver;
use Carbon\CarbonInterface;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ExamObserver::class)]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'title',
        'weight',
        'applied_at',
        'file_path',
    ];

    protected $casts = [
        'applied_at' => 'date',
        'weight' => 'float',
    ];

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

    /**
     * Professor(a) que aplicou a avaliação: a regente ou um especialista da turma.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'teacher_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Carrega `grades_count`, `blank_grades_count` e `grades_avg_score` para exibir o andamento do lançamento.
     */
    #[Scope]
    protected function withGradingProgress(Builder $query): void
    {
        $query->withCount([
            'grades',
            'grades as blank_grades_count' => fn (Builder $query) => $query->blank(),
        ])->withAvg('grades', 'score');
    }

    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->whereDate('applied_at', '>', today());
    }

    #[Scope]
    protected function awaitingGrades(Builder $query): void
    {
        $query->whereDate('applied_at', '<=', today())
            ->whereHas('grades', fn (Builder $query) => $query->blank());
    }

    #[Scope]
    protected function gradesCompleted(Builder $query): void
    {
        $query->whereDate('applied_at', '<=', today())
            ->whereDoesntHave('grades', fn (Builder $query) => $query->blank());
    }

    public function status(): ExamStatus
    {
        if ($this->applied_at->isAfter(today())) {
            return ExamStatus::Scheduled;
        }

        $blankGrades = $this->blank_grades_count ?? $this->grades()->blank()->count();

        return $blankGrades === 0 ? ExamStatus::Completed : ExamStatus::AwaitingGrades;
    }

    /**
     * Distância da data de aplicação em relação a hoje, em dias: "Hoje", "há 3 dias", "daqui a 2 semanas".
     */
    public function appliedAtRelativeLabel(): string
    {
        if ($this->applied_at->isToday()) {
            return 'Hoje';
        }

        return $this->applied_at->locale('pt_BR')->diffForHumans(today(), [
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
            'parts' => 1,
        ]);
    }

    /**
     * Quantidade de linhas do diário já preenchidas, no formato "preenchidas/total".
     */
    public function gradingProgressLabel(): string
    {
        $total = $this->grades_count ?? $this->grades()->count();
        $blankGrades = $this->blank_grades_count ?? $this->grades()->blank()->count();

        return ($total - $blankGrades).'/'.$total;
    }

    /**
     * Garante uma linha no diário de classe para cada aluno da turma.
     */
    public function createMissingGradesForClassroomStudents(): void
    {
        Student::query()->where('classroom_id', $this->classroom_id)->pluck('id')->each(
            fn (int $studentId) => $this->grades()->firstOrCreate(['student_id' => $studentId])
        );
    }
}
