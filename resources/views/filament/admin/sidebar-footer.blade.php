@php
    $semester = now()->month <= 6 ? '1º semestre' : '2º semestre';
    $user = auth()->user();
@endphp

<div
    x-show="$store.sidebar.isOpen"
    x-transition.opacity
    class="mx-4 mb-4 rounded-2xl bg-gradient-to-br from-primary-50 to-warning-50 p-4 ring-1 ring-primary-100 dark:from-primary-500/10 dark:to-warning-500/5 dark:ring-white/10"
>
    <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
        Ano letivo {{ now()->year }}
    </p>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
        {{ $semester }} &middot; {{ now()->locale('pt_BR')->translatedFormat('F') }}
    </p>

    @if ($user)
        <div class="mt-3 border-t border-primary-100 pt-3 dark:border-white/10">
            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->roleLabel() }}</p>
        </div>
    @endif
</div>
