<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    /** Notas abaixo deste valor são consideradas insuficientes (vermelho). */
    public const float LOW_SCORE = 5.0;

    /** Notas a partir deste valor são consideradas boas (verde). Entre as duas, atenção (amarelo). */
    public const float GOOD_SCORE = 7.0;

    protected $fillable = [
        'student_id',
        'exam_id',
        'score',
        'comments',
        'attachments',
    ];

    protected $casts = [
        'score' => 'float',
        'attachments' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->seesAllClassrooms()) {
            $query->whereHas('exam', fn (Builder $query) => $query->visibleTo($user));
        }
    }

    /**
     * Cor do Filament correspondente à faixa da nota, usada de forma consistente em todo o painel.
     */
    public static function colorForScore(?float $score): string
    {
        return match (true) {
            $score === null => 'gray',
            $score >= self::GOOD_SCORE => 'success',
            $score >= self::LOW_SCORE => 'warning',
            default => 'danger',
        };
    }

    public static function formatScore(?float $score): string
    {
        return $score === null ? '—' : number_format($score, 1, ',', '.');
    }

    /**
     * Linhas do diário ainda sem nenhum lançamento (sem nota, observação ou anexo).
     */
    #[Scope]
    protected function blank(Builder $query): void
    {
        $query->whereNull('score')
            ->whereNull('comments')
            ->where(function (Builder $query) {
                $query->whereNull('attachments')->orWhere('attachments', '[]');
            });
    }

    /**
     * Lançamentos atrasados: avaliações já aplicadas cujo diário ainda está em branco.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->blank()->whereHas('exam', function (Builder $query) {
            $query->whereDate('applied_at', '<=', today());
        });
    }
}
