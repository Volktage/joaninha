<?php

namespace App\Models;

use App\Enums\SchoolEventType;
use Carbon\CarbonInterface;
use Database\Factories\SchoolEventFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEvent extends Model
{
    /** @use HasFactory<SchoolEventFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'starts_on',
        'ends_on',
        'classroom_id',
    ];

    protected $casts = [
        'type' => SchoolEventType::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * Turma específica do evento; vazio quando vale para a escola toda.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function lastDay(): CarbonInterface
    {
        return $this->ends_on ?? $this->starts_on;
    }

    /**
     * Eventos que acontecem (total ou parcialmente) entre as duas datas.
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->whereDate('starts_on', '<=', $until)
            ->where(function (Builder $query) use ($from) {
                $query->whereDate('ends_on', '>=', $from)
                    ->orWhere(fn (Builder $query) => $query->whereNull('ends_on')->whereDate('starts_on', '>=', $from));
            });
    }

    /**
     * Eventos da escola toda ou de turmas visíveis ao usuário.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllClassrooms()) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->whereNull('classroom_id')
                ->orWhereHas('classroom', fn (Builder $query) => $query->visibleTo($user));
        });
    }

    /**
     * Dias sem aula (feriados, recessos e dias pedagógicos) entre as datas, no formato Y-m-d.
     *
     * @return array<string, true>
     */
    public static function daysWithoutClasses(CarbonInterface $from, CarbonInterface $until): array
    {
        $days = [];

        static::query()
            ->whereNull('classroom_id')
            ->whereIn('type', array_filter(SchoolEventType::cases(), fn (SchoolEventType $type): bool => $type->suspendsClasses()))
            ->overlapping($from, $until)
            ->get()
            ->each(function (SchoolEvent $event) use (&$days): void {
                for ($day = $event->starts_on->copy(); $day->lte($event->lastDay()); $day = $day->addDay()) {
                    $days[$day->toDateString()] = true;
                }
            });

        return $days;
    }
}
