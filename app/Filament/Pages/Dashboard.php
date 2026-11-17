<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        $greeting = match (true) {
            now()->hour < 12 => 'Bom dia',
            now()->hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        $firstName = str(auth()->user()?->name ?? '')->before(' ')->toString();

        return filled($firstName) ? "{$greeting}, {$firstName}!" : "{$greeting}!";
    }

    public function getSubheading(): string
    {
        $today = now()->locale('pt_BR');
        $semester = $today->month <= 6 ? '1º semestre' : '2º semestre';

        return ucfirst($today->translatedFormat('l, j \d\e F \d\e Y'))." · {$semester} letivo · ".auth()->user()->roleLabel();
    }
}
