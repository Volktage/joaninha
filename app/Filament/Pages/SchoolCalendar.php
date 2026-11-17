<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use App\Models\Exam;
use App\Models\SchoolEvent;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Calendário mensal com os eventos da escola e as avaliações agendadas das turmas visíveis ao usuário.
 */
class SchoolCalendar extends Page
{
    protected string $view = 'filament.pages.school-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::CalendarDays;

    protected static ?string $navigationLabel = 'Calendário';

    protected static ?string $title = 'Calendário escolar';

    protected static ?string $slug = 'calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?int $navigationSort = 3;

    #[Url(as: 'mes')]
    public ?string $month = null;

    public function mount(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
    }

    public function getSubheading(): string
    {
        return ucfirst($this->monthStart()->locale('pt_BR')->translatedFormat('F \d\e Y'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('events')
                ->label('Lista de eventos')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(SchoolEventResource::getUrl()),
            Action::make('createEvent')
                ->label('Novo evento')
                ->icon(Heroicon::OutlinedPlus)
                ->url(SchoolEventResource::getUrl('create'))
                ->visible(fn (): bool => SchoolEventResource::canCreate()),
        ];
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = today()->format('Y-m');
    }

    public function monthStart(): CarbonImmutable
    {
        return rescue(
            fn (): CarbonImmutable => CarbonImmutable::createFromFormat('!Y-m', (string) $this->month)->startOfMonth(),
            CarbonImmutable::today()->startOfMonth(),
            report: false,
        );
    }

    /**
     * Semanas do mês (domingo a sábado), cada dia com seus eventos e avaliações.
     *
     * @return list<list<array{date: CarbonImmutable, inMonth: bool, isToday: bool, isWeekend: bool, noClasses: bool, events: Collection<int, SchoolEvent>, exams: Collection<int, Exam>}>>
     */
    public function weeks(): array
    {
        $monthStart = $this->monthStart();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $events = $this->events($gridStart, $gridEnd);
        $exams = Exam::query()
            ->visibleTo(auth()->user())
            ->with('classroom')
            ->whereBetween('applied_at', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('applied_at')
            ->get()
            ->groupBy(fn (Exam $exam): string => $exam->applied_at->toDateString());

        $weeks = [];

        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $dayEvents = $events->filter(fn (SchoolEvent $event): bool => $day->betweenIncluded($event->starts_on, $event->lastDay()))->values();

            $weeks[intdiv($gridStart->diffInDays($day), 7)][] = [
                'date' => $day,
                'inMonth' => $day->month === $monthStart->month,
                'isToday' => $day->isToday(),
                'isWeekend' => $day->isWeekend(),
                'noClasses' => $dayEvents->contains(fn (SchoolEvent $event): bool => $event->classroom_id === null && $event->type->suspendsClasses()),
                'events' => $dayEvents,
                'exams' => $exams->get($day->toDateString(), collect()),
            ];
        }

        return array_values($weeks);
    }

    /**
     * Eventos do mês exibido, em ordem, para a lista ao lado do calendário.
     *
     * @return Collection<int, SchoolEvent>
     */
    public function monthEvents(): Collection
    {
        return $this->events($this->monthStart(), $this->monthStart()->endOfMonth());
    }

    public function eventUrl(SchoolEvent $event): ?string
    {
        return SchoolEventResource::canView($event) ? SchoolEventResource::getUrl('edit', ['record' => $event]) : null;
    }

    /**
     * @return Collection<int, SchoolEvent>
     */
    private function events(CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return SchoolEvent::query()
            ->visibleTo(auth()->user())
            ->with('classroom')
            ->overlapping($from, $until)
            ->orderBy('starts_on')
            ->get();
    }
}
