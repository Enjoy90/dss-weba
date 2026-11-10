@php
    /** @var \App\Services\Report\ReportDocument|null $document */
    /** @var string|null $problem */
    /** @var int $limit */
@endphp

<style>
    .rep-wrap { overflow-x: auto; }
    .rep-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .rep-table th, .rep-table td { padding: 0.5rem 0.75rem; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--gray-200); }
    .rep-table th { font-weight: 600; background: var(--gray-50); }
    .rep-table .rep-num { text-align: right; }
    .rep-table tr.rep-totals td { font-weight: 600; background: var(--gray-50); }
    .rep-meta { display: grid; grid-template-columns: max-content 1fr; gap: 0.125rem 1rem; font-size: 0.875rem; margin-bottom: 1rem; }
    .rep-meta dt { color: var(--gray-500); }
    .rep-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
    .rep-sub { font-size: 0.875rem; font-weight: 600; margin: 1.25rem 0 0.25rem; }
    .rep-note { font-size: 0.8125rem; color: var(--gray-600); margin-bottom: 0.5rem; }
    :is(.dark) .rep-table th, :is(.dark) .rep-table td { border-bottom-color: var(--gray-700); }
    :is(.dark) .rep-table th, :is(.dark) .rep-table tr.rep-totals td { background: var(--gray-800); }
    :is(.dark) .rep-meta dt, :is(.dark) .rep-note { color: var(--gray-400); }
</style>

@if ($document === null)
    <p class="rep-note">{{ $problem }}</p>
@else
    <div class="rep-title">{{ $document->company->name }}: {{ $document->title }}</div>
    <dl class="rep-meta">
        @foreach ($document->meta as $label => $value)
            <dt>{{ $label }}</dt>
            <dd>{{ $value }}</dd>
        @endforeach
        <dt>Dicetak oleh</dt>
        <dd>{{ $document->printedBy }}</dd>
    </dl>

    @foreach ($document->tables as $table)
        <div class="rep-sub">{{ $table->title }}</div>
        @if (filled($table->note))
            <p class="rep-note">{{ $table->note }}</p>
        @endif

        @if ($table->rows === [])
            <p class="rep-note">{{ $table->empty }}</p>
        @else
            <div class="rep-wrap">
                <table class="rep-table">
                    <thead>
                        <tr>
                            @foreach ($table->columns as $column)
                                <th @class(['rep-num' => $column->isNumeric()])>{{ $column->title }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table->displayRows($limit) as $row)
                            <tr>
                                @foreach ($row as $index => $cell)
                                    <td @class(['rep-num' => $table->columns[$index]->isNumeric()])>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        @if (($totals = $table->displayTotals()) !== null)
                            <tr class="rep-totals">
                                @foreach ($totals as $index => $cell)
                                    <td @class(['rep-num' => $table->columns[$index]->isNumeric()])>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($table->count() > $limit)
                <p class="rep-note">Menampilkan {{ $limit }} baris pertama dari {{ $table->count() }}. Berkas PDF dan Excel memuat semua baris.</p>
            @endif
        @endif
    @endforeach
@endif
