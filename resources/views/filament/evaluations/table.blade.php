@php
    /** @var array<int, string> $headings */
    /** @var array<int, array<int, string>> $rows */
    /** @var string|null $note */
@endphp

<style>
    .eval-table-wrap { overflow-x: auto; }
    .eval-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .eval-table th, .eval-table td { padding: 0.5rem 0.75rem; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--gray-200); }
    .eval-table th { font-weight: 600; background: var(--gray-50); }
    .eval-table td:nth-child(n+3) { text-align: right; }
    .eval-table.eval-text td:nth-child(n+3) { text-align: left; white-space: normal; }
    .eval-note { margin-bottom: 0.75rem; font-size: 0.875rem; color: var(--gray-600); }
    :is(.dark) .eval-table th, :is(.dark) .eval-table td { border-bottom-color: var(--gray-700); }
    :is(.dark) .eval-table th { background: var(--gray-800); }
    :is(.dark) .eval-note { color: var(--gray-400); }
</style>

<div>
    @if (filled($note ?? null))
        <p class="eval-note">{{ $note }}</p>
    @endif

    @if ($rows === [])
        <p class="eval-note">{{ $empty ?? 'Tidak ada data.' }}</p>
    @else
        <div class="eval-table-wrap">
            <table class="eval-table {{ ($textual ?? false) ? 'eval-text' : '' }}">
                <thead>
                    <tr>
                        @foreach ($headings as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
