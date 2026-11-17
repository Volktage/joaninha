<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /** Frequência mínima exigida pela LDB (art. 24, VI) para aprovação no Ensino Fundamental. */
    public const float MINIMUM_RATE = 75.0;

    /** Abaixo deste percentual o aluno já merece atenção da coordenação. */
    public const float WARNING_RATE = 85.0;

    protected $fillable = [
        'classroom_id',
        'student_id',
        'date',
        'status',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => AttendanceStatus::class,
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function colorForRate(?float $rate): string
    {
        return match (true) {
            $rate === null => 'gray',
            $rate < self::MINIMUM_RATE => 'danger',
            $rate < self::WARNING_RATE => 'warning',
            default => 'success',
        };
    }

    public static function formatRate(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate, 1, ',', '.').'%';
    }
}
