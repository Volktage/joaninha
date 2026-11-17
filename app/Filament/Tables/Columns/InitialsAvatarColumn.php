<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar circular com as iniciais (alunos e professores), gerado localmente em SVG (sem serviços externos).
 */
class InitialsAvatarColumn
{
    /** @var list<array{0: string, 1: string}> Pares de fundo e texto, em tons suaves. */
    private const array PALETTE = [
        ['#ffe4e6', '#be123c'],
        ['#fef3c7', '#b45309'],
        ['#e0f2fe', '#0369a1'],
        ['#d1fae5', '#047857'],
        ['#ede9fe', '#6d28d9'],
        ['#fce7f3', '#be185d'],
        ['#ffedd5', '#c2410c'],
        ['#ccfbf1', '#0f766e'],
    ];

    /**
     * @param  string  $nameAttribute  Caminho até o nome a partir do registro da linha (ex: "student.name").
     */
    public static function make(string $nameAttribute = 'name'): ImageColumn
    {
        return ImageColumn::make('avatar')
            ->label('')
            ->state(fn (Model $record): string => static::url((string) data_get($record, $nameAttribute)))
            ->circular()
            ->imageSize(36)
            ->grow(false)
            ->width('1%');
    }

    public static function url(string $name): string
    {
        $words = collect(explode(' ', trim($name)))->filter();
        $initials = mb_strtoupper(mb_substr($words->first() ?? '?', 0, 1).mb_substr($words->count() > 1 ? $words->last() : '', 0, 1));
        [$background, $foreground] = self::PALETTE[crc32($name) % count(self::PALETTE)];

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                <rect width="64" height="64" fill="{$background}"/>
                <text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Nunito, ui-sans-serif, system-ui, sans-serif" font-size="26" font-weight="700" fill="{$foreground}">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
