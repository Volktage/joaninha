@php
    use App\Models\Attendance;
    use App\Models\Grade;

    $scoreClass = fn (?float $score): string => match (Grade::colorForScore($score)) {
        'success' => 'good',
        'warning' => 'attention',
        'danger' => 'low',
        default => 'empty',
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=nunito:400,600,700,800" rel="stylesheet">
    <style>
        :root {
            --ink: #1c1917;
            --muted: #78716c;
            --line: #e7e5e4;
            --soft: #fafaf9;
            --rose: #e11d48;
            --good: #047857;
            --attention: #b45309;
            --low: #b91c1c;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f5f5f4;
            color: var(--ink);
            font-family: 'Nunito', ui-sans-serif, system-ui, sans-serif;
            font-size: 12.5px;
            line-height: 1.45;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 12px;
            background: rgba(255, 255, 255, .92);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(6px);
        }

        .toolbar button, .toolbar a {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            font: 600 13px 'Nunito', sans-serif;
            padding: 8px 14px;
            text-decoration: none;
        }

        .toolbar button.primary {
            background: var(--rose);
            border-color: var(--rose);
            color: #fff;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 16mm 15mm;
            background: #fff;
            box-shadow: 0 10px 30px -12px rgba(0, 0, 0, .18);
            page-break-after: always;
        }

        .page:last-child { page-break-after: auto; }

        header.school {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--rose);
        }

        .brand { display: flex; align-items: center; gap: 10px; }
        .brand strong { display: block; font-size: 20px; font-weight: 800; letter-spacing: -.02em; }
        .brand span { color: var(--rose); font-size: 9.5px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 17px; font-weight: 800; }
        .doc-title p { margin: 2px 0 0; color: var(--muted); }

        .info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 16px;
            margin: 14px 0 18px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--soft);
        }

        .info dt { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .info dd { margin: 0; font-weight: 600; }
        .info .wide { grid-column: span 2; }

        h2 {
            margin: 18px 0 8px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; }
        th { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .05em; text-align: left; text-transform: uppercase; }
        th, td { padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        td.num, th.num { text-align: center; white-space: nowrap; }
        tr.bimester-total td { background: var(--soft); font-weight: 700; }

        .score { display: inline-block; min-width: 38px; padding: 1px 8px; border-radius: 999px; font-weight: 800; text-align: center; }
        .score.good { background: #d1fae5; color: var(--good); }
        .score.attention { background: #fef3c7; color: var(--attention); }
        .score.low { background: #fee2e2; color: var(--low); }
        .score.empty { color: var(--muted); }

        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 14px; }
        .summary div { padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; }
        .summary small { display: block; color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .summary b { font-size: 18px; font-weight: 800; }

        .report { margin-bottom: 10px; padding: 10px 12px; border-left: 3px solid var(--rose); border-radius: 0 10px 10px 0; background: var(--soft); }
        .report header { display: flex; justify-content: space-between; margin-bottom: 4px; font-weight: 700; }
        .report header span { color: var(--muted); font-weight: 600; }
        .report p { margin: 0; }

        .note { margin-top: 10px; color: var(--muted); font-size: 11px; }
        .alert { color: var(--low); font-weight: 700; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 46px; text-align: center; }
        .signatures div { padding-top: 6px; border-top: 1px solid var(--ink); font-size: 11px; }
        .signatures span { display: block; color: var(--muted); font-size: 10px; }

        footer.emission { margin-top: 18px; color: var(--muted); font-size: 10px; text-align: center; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <nav class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Imprimir ou salvar em PDF</button>
        <a href="javascript:history.back()">Voltar ao painel</a>
    </nav>

    @foreach ($reportCards as $card)
        @php
            $student = $card['student'];
            $classroom = $student->classroom;
            $attendance = $card['attendance'];
        @endphp

        <section class="page">
            <header class="school">
                <div class="brand">
                    <svg width="38" height="38" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <path d="M11 7.5 8.5 4M21 7.5 23.5 4" stroke="#18181b" stroke-width="1.6" stroke-linecap="round" />
                        <circle cx="16" cy="10" r="5" fill="#18181b" />
                        <ellipse cx="16" cy="19" rx="10.5" ry="10" fill="#e11d48" />
                        <path d="M16 9.5V29" stroke="#18181b" stroke-width="1.4" />
                        <circle cx="11" cy="16" r="1.9" fill="#18181b" />
                        <circle cx="21" cy="16" r="1.9" fill="#18181b" />
                        <circle cx="10.5" cy="22.5" r="1.6" fill="#18181b" />
                        <circle cx="21.5" cy="22.5" r="1.6" fill="#18181b" />
                    </svg>
                    <div>
                        <strong>Escola Joaninha</strong>
                        <span>Educação Infantil e Fundamental I</span>
                    </div>
                </div>
                <div class="doc-title">
                    <h1>{{ $card['isGraded'] ? 'Boletim Escolar' : 'Relatório de Desenvolvimento' }}</h1>
                    <p>Ano letivo {{ $classroom->year }}</p>
                </div>
            </header>

            <dl class="info">
                <div class="wide"><dt>Aluno(a)</dt><dd>{{ $student->name }}</dd></div>
                <div><dt>Matrícula</dt><dd>{{ $student->registration_number }}</dd></div>
                <div><dt>Turma</dt><dd>{{ $classroom->name }} · {{ $classroom->period?->getLabel() }}</dd></div>
                <div><dt>Professora regente</dt><dd>{{ $classroom->homeroomTeacher?->name ?? '—' }}</dd></div>
                <div><dt>Nascimento</dt><dd>{{ $student->birth_date ? $student->birth_date->format('d/m/Y').' ('.$student->age().' anos)' : '—' }}</dd></div>
                <div class="wide"><dt>Responsável</dt><dd>{{ $student->guardian_name ? $student->guardian_name.($student->guardian_relationship ? ' ('.$student->guardian_relationship->getLabel().')' : '') : '—' }}</dd></div>
                <div><dt>Contato</dt><dd>{{ $student->guardian_phone ?? '—' }}</dd></div>
                @if ($student->isSpecialEducation())
                    <div class="wide"><dt>Educação Especial (AEE)</dt><dd>{{ $student->special_education->getLabel() }}</dd></div>
                @endif
            </dl>

            @if ($card['isGraded'])
                <h2>Avaliações por bimestre</h2>

                @forelse ($card['bimesters'] as $bimester)
                    <table>
                        <thead>
                            <tr>
                                <th>{{ $bimester['bimester']->getLabel() }}</th>
                                <th class="num">Data</th>
                                <th class="num">Peso</th>
                                <th class="num">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bimester['grades'] as $grade)
                                <tr>
                                    <td>
                                        {{ $grade->exam->title }}
                                        @if ($grade->comments)
                                            <br><small style="color: var(--muted)">{{ $grade->comments }}</small>
                                        @endif
                                    </td>
                                    <td class="num">{{ $grade->exam->applied_at->format('d/m') }}</td>
                                    <td class="num">×{{ number_format($grade->exam->weight, 1, ',', '.') }}</td>
                                    <td class="num"><span class="score {{ $scoreClass($grade->score) }}">{{ Grade::formatScore($grade->score) }}</span></td>
                                </tr>
                            @endforeach
                            <tr class="bimester-total">
                                <td colspan="3">Média ponderada do {{ $bimester['bimester']->getLabel() }}</td>
                                <td class="num"><span class="score {{ $scoreClass($bimester['average']) }}">{{ Grade::formatScore($bimester['average']) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                @empty
                    <p class="note">Nenhuma avaliação aplicada até o momento.</p>
                @endforelse
            @else
                <h2>Relatórios e registros de observação</h2>

                @forelse ($card['reports'] as $report)
                    <article class="report">
                        <header>{{ $report->exam->title }} <span>{{ $report->exam->applied_at->format('d/m/Y') }}</span></header>
                        <p>{{ $report->comments }}</p>
                    </article>
                @empty
                    <p class="note">Nenhum relatório escrito até o momento.</p>
                @endforelse

                <p class="note">Na Educação Infantil a avaliação é feita por acompanhamento e registro do desenvolvimento, sem atribuição de notas (LDB, art. 31).</p>
            @endif

            <h2>Resumo</h2>
            <div class="summary">
                @if ($card['isGraded'])
                    <div><small>Média ponderada</small><b><span class="score {{ $scoreClass($card['average']) }}">{{ Grade::formatScore($card['average']) }}</span></b></div>
                @endif
                <div><small>Frequência</small><b class="{{ $attendance['rate'] !== null && $attendance['rate'] < Attendance::MINIMUM_RATE ? 'alert' : '' }}">{{ Attendance::formatRate($attendance['rate']) }}</b></div>
                <div><small>Dias letivos registrados</small><b>{{ $attendance['days'] }}</b></div>
                <div><small>Faltas (justificadas)</small><b>{{ $attendance['absences'] + $attendance['justified'] }} ({{ $attendance['justified'] }})</b></div>
            </div>

            @if ($attendance['rate'] !== null && $attendance['rate'] < Attendance::MINIMUM_RATE)
                <p class="note alert">Frequência abaixo do mínimo de 75% exigido para aprovação (LDB, art. 24, VI). A família será chamada pela coordenação.</p>
            @endif

            <div class="signatures">
                <div>{{ $classroom->homeroomTeacher?->name ?? 'Professora regente' }}<span>Professora regente</span></div>
                <div>{{ $director ?? 'Direção' }}<span>Direção</span></div>
                <div>&nbsp;<span>Responsável</span></div>
            </div>

            <footer class="emission">Emitido em {{ now()->format('d/m/Y \à\s H:i') }} pelo sistema Joaninha · As notas consideram as avaliações aplicadas até esta data.</footer>
        </section>
    @endforeach
</body>
</html>
