@props([
    'columnSpan' => [],
    'columnStart' => [],
    'variant' => 'table',
    'heading' => null,
    'description' => null,
])

@php
    $wrapper = (new \Illuminate\View\ComponentAttributeBag)
        ->gridColumn($columnSpan, $columnStart)
        ->class(['joaninha-widget-skeleton']);

    $barHeights = [62, 78, 54, 88, 66, 72, 58, 84, 70, 64];
@endphp

<div {{ $wrapper }} role="status" aria-live="polite" aria-label="Carregando {{ $heading ?? 'informações' }}">
    @if ($variant === 'stats')
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach (range(1, 4) as $card)
                <div class="fi-section min-h-[11rem] space-y-4 p-6">
                    <div class="joaninha-skeleton h-3.5 w-28"></div>
                    <div class="joaninha-skeleton h-8 w-16"></div>
                    <div class="joaninha-skeleton h-3 w-40"></div>
                </div>
            @endforeach
        </div>
    @else
        <section class="fi-section overflow-hidden">
            <header class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                @if (filled($heading))
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $heading }}</h3>
                @else
                    <div class="joaninha-skeleton h-4 w-48"></div>
                @endif

                @if (filled($description))
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                @endif
            </header>

            <div class="p-6">
                @switch($variant)
                    @case('bar')
                        <div class="joaninha-chart-area flex items-end justify-around gap-3 border-b border-l border-gray-100 px-3 pb-2 dark:border-white/10">
                            @foreach ($barHeights as $height)
                                <div class="joaninha-skeleton w-full max-w-12 rounded-t-lg rounded-b-none" style="height: {{ $height }}%"></div>
                            @endforeach
                        </div>
                        @break

                    @case('doughnut')
                    @case('pie')
                        <div class="joaninha-chart-area flex items-center justify-center gap-8">
                            <div class="joaninha-skeleton aspect-square h-[85%] rounded-full [mask:radial-gradient(circle,transparent_42%,black_43%)]"></div>
                            <div class="hidden space-y-3 sm:block">
                                @foreach (range(1, 4) as $item)
                                    <div class="joaninha-skeleton h-3 w-20"></div>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case('line')
                        <div class="joaninha-chart-area joaninha-chart-area-tall relative flex flex-col justify-between border-b border-l border-gray-100 py-2 dark:border-white/10">
                            @foreach (range(1, 5) as $line)
                                <div class="border-t border-dashed border-gray-100 dark:border-white/5"></div>
                            @endforeach
                            <svg class="joaninha-skeleton-line absolute inset-x-0 top-1/4 h-1/2 w-full" viewBox="0 0 400 100" preserveAspectRatio="none" aria-hidden="true">
                                <path d="M0,70 C40,40 80,80 120,55 S200,30 240,50 S320,75 400,35" fill="none" stroke-width="4" stroke-linecap="round" />
                                <path d="M0,45 C50,65 90,35 140,50 S220,70 260,40 S340,30 400,55" fill="none" stroke-width="4" stroke-linecap="round" opacity=".6" />
                            </svg>
                        </div>
                        @break

                    @default
                        <div class="space-y-4">
                            @foreach (range(1, 5) as $row)
                                <div class="flex items-center gap-4">
                                    <div class="joaninha-skeleton h-9 w-9 shrink-0 rounded-full"></div>
                                    <div class="flex-1 space-y-2">
                                        <div class="joaninha-skeleton h-3.5 w-3/5"></div>
                                        <div class="joaninha-skeleton h-3 w-2/5"></div>
                                    </div>
                                    <div class="joaninha-skeleton h-6 w-14 rounded-full"></div>
                                </div>
                            @endforeach
                        </div>
                @endswitch
            </div>
        </section>
    @endif

    <span class="sr-only">Carregando…</span>
</div>
