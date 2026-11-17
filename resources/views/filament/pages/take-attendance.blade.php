@php
    use App\Enums\AttendanceStatus;
    use App\Filament\Tables\Columns\InitialsAvatarColumn;
    use Filament\Support\Icons\Heroicon;

    $noClassReason = $this->noClassReason();
    $summary = $this->summary();
    $isFutureLimit = $this->selectedDate()->gte(today());

    $selectedStyles = [
        'present' => 'bg-success-600 text-white ring-success-600 shadow-sm shadow-success-600/30',
        'late' => 'bg-warning-500 text-white ring-warning-500 shadow-sm shadow-warning-500/30',
        'absent' => 'bg-danger-600 text-white ring-danger-600 shadow-sm shadow-danger-600/30',
        'justified' => 'bg-info-600 text-white ring-info-600 shadow-sm shadow-info-600/30',
    ];

    $summaryStyles = [
        'present' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
        'late' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400',
        'absent' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400',
        'justified' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400',
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <label class="grid gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">Turma</span>
                <x-filament::input.wrapper :prefix-icon="Heroicon::OutlinedAcademicCap">
                    <x-filament::input.select wire:model.live="classroomId">
                        @foreach ($this->classrooms as $option)
                            <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->period?->getLabel() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <div class="grid gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">Data</span>
                <div class="flex items-center gap-2">
                    <x-filament::icon-button :icon="Heroicon::OutlinedChevronLeft" color="gray" wire:click="shiftDate(-1)" label="Dia anterior" />
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" />
                    </x-filament::input.wrapper>
                    <x-filament::icon-button :icon="Heroicon::OutlinedChevronRight" color="gray" wire:click="shiftDate(1)" label="Próximo dia" :disabled="$isFutureLimit" />
                    <x-filament::button color="gray" size="sm" wire:click="goToToday">Hoje</x-filament::button>
                </div>
            </div>
        </div>

        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{ ucfirst($this->selectedDate()->locale('pt_BR')->translatedFormat('l, j \d\e F \d\e Y')) }}
        </p>
    </x-filament::section>

    @if (! $this->classroom)
        <x-filament::empty-state
            heading="Nenhuma turma disponível"
            description="Você ainda não está vinculado(a) a nenhuma turma. Fale com a coordenação."
            :icon="Heroicon::OutlinedAcademicCap"
        />
    @elseif ($noClassReason)
        <x-filament::callout
            color="warning"
            :icon="Heroicon::OutlinedCalendarDays"
            heading="Não há aula nesta data"
            :description="$noClassReason . '. Escolha um dia letivo para fazer a chamada.'"
        />
    @else
        @if ($this->existingRecordInfo)
            <x-filament::callout color="info" :icon="Heroicon::OutlinedCheckBadge" :description="$this->existingRecordInfo" />
        @endif

        <x-filament::section :heading="$this->classroom->name . ' · ' . $this->students->count() . ' alunos'" :icon="Heroicon::OutlinedUsers">
            <x-slot name="afterHeader">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (AttendanceStatus::cases() as $status)
                        <span @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $summaryStyles[$status->value]])>
                            {{ $status->getLabel() }}: {{ $summary[$status->value] }}
                        </span>
                    @endforeach
                </div>
            </x-slot>

            <div class="-mx-6 -my-6 divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($this->students as $student)
                    @php($current = $statuses[$student->id] ?? 'present')

                    <div wire:key="attendance-{{ $student->id }}" class="flex flex-col gap-3 px-6 py-3 transition hover:bg-gray-50 sm:flex-row sm:items-center dark:hover:bg-white/5">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <img src="{{ InitialsAvatarColumn::url($student->name) }}" alt="" class="h-9 w-9 shrink-0 rounded-full">
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 truncate font-medium text-gray-950 dark:text-white">
                                    {{ $student->name }}
                                    @if ($student->isSpecialEducation())
                                        <span class="rounded-md bg-info-50 px-1.5 py-0.5 text-[10px] font-bold text-info-700 ring-1 ring-inset ring-info-600/20 dark:bg-info-500/10 dark:text-info-300" title="{{ $student->special_education->getLabel() }}">AEE</span>
                                    @endif
                                    @if ($student->hasHealthAlert())
                                        <span title="{{ $student->allergies ? 'Alergia: '.$student->allergies : $student->health_notes }}">
                                            <x-filament::icon :icon="Heroicon::ExclamationTriangle" class="h-4 w-4 text-danger-500" />
                                        </span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Matrícula {{ $student->registration_number }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:items-end">
                            <div class="inline-flex flex-wrap gap-1.5" role="radiogroup" aria-label="Situação de {{ $student->name }}">
                                @foreach (AttendanceStatus::cases() as $status)
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked="{{ $current === $status->value ? 'true' : 'false' }}"
                                        wire:click="setStatus({{ $student->id }}, '{{ $status->value }}')"
                                        @class([
                                            'inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 transition duration-150 active:scale-95',
                                            $selectedStyles[$status->value] => $current === $status->value,
                                            'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10' => $current !== $status->value,
                                        ])
                                    >
                                        <x-filament::icon :icon="$status->getIcon()" class="h-4 w-4" />
                                        {{ $status->getLabel() }}
                                    </button>
                                @endforeach
                            </div>

                            @if (! AttendanceStatus::from($current)->countsAsPresence())
                                <x-filament::input.wrapper class="w-full sm:w-80">
                                    <x-filament::input type="text" wire:model.blur="notes.{{ $student->id }}" placeholder="Observação (ex.: atestado entregue)" maxlength="255" />
                                </x-filament::input.wrapper>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-500">Nenhum aluno matriculado nesta data.</p>
                @endforelse
            </div>
        </x-filament::section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-filament::button color="gray" :icon="Heroicon::OutlinedCheckCircle" wire:click="markEveryoneAs('present')">
                Marcar todos como presentes
            </x-filament::button>

            <x-filament::button :icon="Heroicon::OutlinedCheck" wire:click="save" wire:loading.attr="disabled" size="lg">
                Salvar chamada
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
