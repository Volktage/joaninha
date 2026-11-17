@php
    use App\Filament\Resources\Exams\ExamResource;
    use Filament\Support\Icons\Heroicon;

    $weeks = $this->weeks();
    $monthEvents = $this->monthEvents();

    $chipStyles = [
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-white/10 dark:text-gray-300',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-300',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300',
        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300',
    ];

    $dotStyles = [
        'danger' => 'bg-danger-500', 'gray' => 'bg-gray-400', 'info' => 'bg-info-500',
        'primary' => 'bg-primary-500', 'warning' => 'bg-warning-500', 'success' => 'bg-success-500',
    ];

    $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-filament::icon-button :icon="Heroicon::OutlinedChevronLeft" color="gray" wire:click="previousMonth" label="Mês anterior" />
            <x-filament::button color="gray" size="sm" wire:click="currentMonth">Hoje</x-filament::button>
            <x-filament::icon-button :icon="Heroicon::OutlinedChevronRight" color="gray" wire:click="nextMonth" label="Próximo mês" />
            <h2 class="ms-2 text-lg font-bold tracking-tight text-gray-950 dark:text-white">
                {{ ucfirst($this->monthStart()->locale('pt_BR')->translatedFormat('F \d\e Y')) }}
            </h2>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-danger-500"></span>Feriado</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>Sem aula</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-info-500"></span>Reunião</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-warning-500"></span>Festa</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-success-500"></span>Passeio</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-500"></span>Avaliação</span>
        </div>
    </div>

    {{-- Grade do mês (telas médias e grandes) --}}
    <div class="hidden overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 md:block dark:bg-gray-900 dark:ring-white/10">
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
            @foreach ($weekdays as $weekday)
                <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $weekday }}</div>
            @endforeach
        </div>

        @foreach ($weeks as $week)
            <div class="grid grid-cols-7 border-b border-gray-100 last:border-b-0 dark:border-white/5">
                @foreach ($week as $day)
                    <div @class([
                        'min-h-28 border-e border-gray-100 p-1.5 last:border-e-0 dark:border-white/5',
                        'bg-gray-50/70 dark:bg-white/[0.02]' => $day['isWeekend'] || ! $day['inMonth'],
                        'bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,rgb(0_0_0/0.025)_6px,rgb(0_0_0/0.025)_12px)]' => $day['noClasses'],
                    ])>
                        <div class="mb-1 flex items-center justify-between">
                            <span @class([
                                'inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-primary-600 text-white shadow-sm shadow-primary-600/30' => $day['isToday'],
                                'text-gray-900 dark:text-white' => ! $day['isToday'] && $day['inMonth'],
                                'text-gray-400 dark:text-gray-600' => ! $day['isToday'] && ! $day['inMonth'],
                            ])>{{ $day['date']->day }}</span>
                        </div>

                        <div class="space-y-1">
                            @foreach ($day['events'] as $event)
                                @php($url = $this->eventUrl($event))
                                <a
                                    @if ($url) href="{{ $url }}" wire:navigate @endif
                                    title="{{ $event->title }}{{ $event->classroom ? ' · '.$event->classroom->name : '' }}{{ $event->description ? ' — '.$event->description : '' }}"
                                    @class([
                                        'flex items-center gap-1 truncate rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset transition hover:brightness-95',
                                        $chipStyles[$event->type->getColor()],
                                    ])
                                >
                                    <x-filament::icon :icon="$event->type->getIcon()" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ $event->title }}</span>
                                </a>
                            @endforeach

                            @if ($day['exams']->isNotEmpty())
                                <div
                                    x-data="{ open: false }"
                                    class="relative"
                                >
                                    <button type="button" x-on:click="open = ! open" class="flex w-full items-center gap-1 truncate rounded-md bg-primary-600/10 px-1.5 py-0.5 text-left text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:text-primary-300">
                                        <x-filament::icon :icon="Heroicon::OutlinedClipboardDocumentCheck" class="h-3 w-3 shrink-0" />
                                        {{ $day['exams']->count() === 1 ? '1 avaliação' : $day['exams']->count().' avaliações' }}
                                    </button>
                                    <div
                                        x-show="open"
                                        x-on:click.outside="open = false"
                                        x-transition
                                        class="absolute start-0 top-6 z-20 w-64 space-y-1 rounded-xl bg-white p-2 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                                        x-cloak
                                    >
                                        @foreach ($day['exams'] as $exam)
                                            <a href="{{ ExamResource::getUrl('edit', ['record' => $exam, 'relation' => '0']) }}" wire:navigate class="block rounded-lg px-2 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-white/5">
                                                <span class="block font-semibold text-gray-900 dark:text-white">{{ $exam->title }}</span>
                                                <span class="text-gray-500 dark:text-gray-400">{{ $exam->classroom->name }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Agenda do mês (lista, também usada no celular) --}}
    <x-filament::section heading="Eventos do mês" :icon="Heroicon::OutlinedListBullet" :description="$monthEvents->isEmpty() ? 'Nenhum evento neste mês.' : null">
        @if ($monthEvents->isNotEmpty())
            <ul class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($monthEvents as $event)
                    @php($url = $this->eventUrl($event))
                    <li class="flex items-start gap-3 py-2.5">
                        <div class="w-14 shrink-0 text-center">
                            <span class="block text-lg font-bold leading-none text-gray-950 dark:text-white">{{ $event->starts_on->format('d') }}</span>
                            <span class="text-[11px] font-semibold uppercase text-gray-500">{{ $event->starts_on->locale('pt_BR')->translatedFormat('D') }}</span>
                        </div>
                        <span @class(['mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full', $dotStyles[$event->type->getColor()]])></span>
                        <div class="min-w-0 flex-1">
                            @if ($url)
                                <a href="{{ $url }}" wire:navigate class="font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $event->title }}</a>
                            @else
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $event->title }}</span>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $event->type->getLabel() }}
                                · {{ $event->classroom?->name ?? 'Escola toda' }}
                                @if ($event->ends_on)
                                    · até {{ $event->ends_on->format('d/m') }}
                                @endif
                            </p>
                            @if ($event->description)
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $event->description }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
