<x-filament-widgets::widget>
    <style>
        .latest-eval-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .latest-eval-facts { display: grid; grid-template-columns: max-content 1fr; gap: 0.25rem 1rem; margin-top: 0.75rem; font-size: 0.875rem; }
        .latest-eval-facts dt { color: var(--gray-500); }
        .latest-eval-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-top: 1rem; }
        .latest-eval-note { font-size: 0.875rem; color: var(--gray-600); }
        :is(.dark) .latest-eval-facts dt, :is(.dark) .latest-eval-note { color: var(--gray-400); }
    </style>

    <x-filament::section heading="Perhitungan terakhir">
        @if ($evaluation === null)
            <p class="latest-eval-note">Belum ada perhitungan prioritas.</p>
            @if (! $canRun)
                <p class="latest-eval-note">Manajer Produksi menjalankannya dari menu Prioritas.</p>
            @endif
        @else
            <div class="latest-eval-head">
                <x-filament::badge :color="$evaluation->status->color()">{{ $evaluation->status->label() }}</x-filament::badge>
                <span style="font-weight: 600;">{{ $evaluation->name }}</span>
            </div>
            <dl class="latest-eval-facts">
                <dt>Waktu hitung</dt>
                <dd>{{ $evaluation->evaluated_at->format('d/m/Y H:i') }}</dd>
                <dt>Pemicu</dt>
                <dd>{{ $evaluation->trigger->label() }}</dd>
                <dt>Order dihitung</dt>
                <dd>{{ $evaluation->order_count }}</dd>
            </dl>
        @endif

        <div class="latest-eval-actions">
            @if ($evaluation !== null)
                <x-filament::link :href="$evaluationUrl" icon="heroicon-m-arrow-right" icon-position="after">Buka hasil</x-filament::link>
            @endif
            @if ($canRun)
                <x-filament::button :href="$runUrl" tag="a" icon="heroicon-m-calculator">Jalankan Perhitungan</x-filament::button>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
