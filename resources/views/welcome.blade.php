<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Joaninha</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @fonts
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                /* Fallback styles */
                body { font-family: 'Instrument Sans', sans-serif; }
            </style>
        @endif
        <style>
            .joaninha-red { color: #e11d48; }
            .joaninha-bg-red { background-color: #e11d48; }
            .joaninha-light-bg { background-color: #fff1f2; }
            .joaninha-dark-bg { background-color: #4c0519; }
        </style>
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6">
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">Register</a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow">
            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
                <div class="text-[13px] leading-[20px] flex-1 p-6 pb-6 lg:p-20 lg:pb-10 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-bl-lg rounded-br-lg lg:rounded-tl-lg lg:rounded-br-none">
                    <div class="mb-6 flex items-center gap-4">
                        <img src="{{ asset('images/joaninha-icon.svg') }}" class="w-16 h-16" alt="Joaninha Logo">
                        <h1 class="text-4xl font-black joaninha-red tracking-tight">Joaninha</h1>
                    </div>
                    
                    <h2 class="text-xl font-bold mb-2">Bem-vindo ao Jardim da Educação</h2>
                    <p class="mb-6 text-[#706f6c] dark:text-[#A1A09A] text-base">Organize suas turmas, alunos e notas com a simplicidade e o cuidado que sua escola merece.</p>
                    
                    <div class="space-y-4 mb-10">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 flex-shrink-0 w-5 h-5 rounded-full joaninha-bg-red flex items-center justify-center shadow-sm">
                                <div class="w-2 h-2 bg-white rounded-full"></div>
                            </div>
                            <div>
                                <h3 class="font-bold">Gestão Completa</h3>
                                <p class="text-gray-500 dark:text-gray-400">Controle total sobre salas de aula e registros de estudantes.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-1 flex-shrink-0 w-5 h-5 rounded-full joaninha-bg-red flex items-center justify-center shadow-sm">
                                <div class="w-2 h-2 bg-white rounded-full"></div>
                            </div>
                            <div>
                                <h3 class="font-bold">Avaliações Inteligentes</h3>
                                <p class="text-gray-500 dark:text-gray-400">Lance provas e acompanhe o desempenho com relatórios claros.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <a href="/admin" class="inline-block px-10 py-4 joaninha-bg-red hover:bg-[#be123c] text-white text-lg font-black rounded-xl transition-all shadow-lg hover:shadow-red-200/50 transform hover:-translate-y-0.5 active:translate-y-0">
                            ACESSAR PAINEL
                        </a>
                    </div>

                    <p class="mt-10 text-[#706f6c] dark:text-[#A1A09A] text-xs">
                        Joaninha v1.0 • {{ app()->version() }}
                    </p>
                </div>
                
                <div class="joaninha-light-bg dark:joaninha-dark-bg relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg aspect-[335/364] lg:aspect-auto w-full lg:w-[438px] shrink-0 overflow-hidden flex items-center justify-center p-12">
                    <img src="{{ asset('images/joaninha-icon.svg') }}" class="w-72 h-72 drop-shadow-[0_20px_50px_rgba(225,29,72,0.3)] animate-pulse" style="animation-duration: 4s;" alt="Joaninha Big">
                    <div class="absolute inset-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg shadow-[inset_0px_0px_0px_1px_rgba(225,29,72,0.1)]"></div>
                    
                    <!-- Decorative Spots -->
                    <div class="absolute top-10 right-10 w-4 h-4 rounded-full bg-[#18181b] opacity-10"></div>
                    <div class="absolute bottom-20 left-10 w-6 h-6 rounded-full bg-[#18181b] opacity-10"></div>
                    <div class="absolute top-40 left-20 w-3 h-3 rounded-full bg-[#18181b] opacity-10"></div>
                </div>
            </main>
        </div>
    </body>
</html>
