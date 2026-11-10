<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 28px 32px 44px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; }
        .kop { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 10px; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 64px; }
        .kop .logo img { max-width: 56px; max-height: 56px; }
        .kop .name { font-size: 15px; font-weight: bold; }
        .kop .line { font-size: 9px; color: #374151; }
        h1 { font-size: 13px; margin: 0 0 6px 0; }
        h2 { font-size: 10.5px; margin: 14px 0 4px 0; }
        table.meta { border-collapse: collapse; margin-bottom: 4px; }
        table.meta td { padding: 1px 12px 1px 0; vertical-align: top; }
        table.meta td.label { color: #4b5563; width: 130px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.data th, table.data td { border: 1px solid #9ca3af; padding: 3px 5px; }
        table.data th { background: #e5e7eb; text-align: left; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data tr.totals td { font-weight: bold; background: #f3f4f6; }
        .num { text-align: right; }
        .note { color: #4b5563; margin: 2px 0 4px 0; }
        .empty { color: #4b5563; font-style: italic; margin: 4px 0; }
        table.sign { width: 100%; margin-top: 26px; page-break-inside: avoid; }
        table.sign td { width: 50%; text-align: center; vertical-align: top; }
        .sign .space { height: 56px; }
        .sign .who { font-weight: bold; text-decoration: underline; }
        .printed { color: #4b5563; margin-top: 12px; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            @if ($logo = $document->company->logoDataUri())
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="name">{{ $document->company->name }}</div>
                @foreach ($document->company->lines() as $line)
                    <div class="line">{{ $line }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    <h1>{{ $document->title }}</h1>
    <table class="meta">
        @foreach ($document->meta as $label => $value)
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
        <tr><td class="label">Tanggal cetak</td><td>{{ $document->printedAt->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="label">Dicetak oleh</td><td>{{ $document->printedBy }}</td></tr>
    </table>

    @foreach ($document->tables as $table)
        <h2>{{ $table->title }}</h2>
        @if (filled($table->note))
            <div class="note">{{ $table->note }}</div>
        @endif

        @if ($table->rows === [])
            <div class="empty">{{ $table->empty }}</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($table->columns as $column)
                            <th @class(['num' => $column->isNumeric()])>{{ $column->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table->displayRows() as $row)
                        <tr>
                            @foreach ($row as $index => $cell)
                                <td @class(['num' => $table->columns[$index]->isNumeric()])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if (($totals = $table->displayTotals()) !== null)
                        <tr class="totals">
                            @foreach ($totals as $index => $cell)
                                <td @class(['num' => $table->columns[$index]->isNumeric()])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif
    @endforeach

    <table class="sign">
        <tr>
            <td>
                Dibuat oleh,
                <div class="space"></div>
                <div class="who">{{ $document->printedBy }}</div>
            </td>
            <td>
                Diketahui oleh,
                <div class="space"></div>
                <div class="who">Pimpinan</div>
            </td>
        </tr>
    </table>
</body>
</html>
