<?php

namespace App\Services\Notifications;

use App\Enums\OrderStatus;
use App\Enums\RankGroup;
use App\Enums\UserRole;
use App\Filament\Pages\AssessmentMatrix;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Evaluation\EvaluationSettings;
use App\Services\Evaluation\EvaluationWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Creates the in-app notifications of PRD chapter 19 (N1 to N10). They are saved for the bell icon in the top bar.
 * Each notification carries its code (N1 to N10) so the list of a user can be told apart and tested.
 */
class EvaluationNotifier
{
    private const PANEL = 'admin';

    /**
     * The notification that follows a step of the approval flow: submitted (N1), approved (N2), rejected (N3), applied (N4).
     */
    public function afterStep(string $action, Evaluation $evaluation, ?int $submitterId): void
    {
        match ($action) {
            'evaluation.submitted' => $this->send('N1', $this->usersWithRole(UserRole::Leader), 'Evaluasi menunggu persetujuan Anda', $evaluation->name.' sudah diajukan dan perlu disetujui atau ditolak.', 'heroicon-o-clock', 'warning', $this->evaluationUrl($evaluation), 'Tinjau evaluasi'),
            'evaluation.approved' => $this->send('N2', $this->usersById($submitterId), 'Evaluasi disetujui', $evaluation->name.' sudah disetujui dan siap diterapkan.', 'heroicon-o-check-circle', 'success', $this->evaluationUrl($evaluation), 'Buka evaluasi'),
            'evaluation.rejected' => $this->send('N3', $this->usersById($submitterId), 'Evaluasi ditolak', $evaluation->name.' ditolak. Catatan: '.$evaluation->rejection_note, 'heroicon-o-x-circle', 'danger', $this->evaluationUrl($evaluation), 'Buka evaluasi'),
            'evaluation.applied' => $this->send('N4', $this->usersWithRole(UserRole::Supervisor), 'Daftar prioritas baru ditetapkan', $evaluation->name.' sudah diterapkan. Order berlabel Prioritas Utama berubah.', 'heroicon-o-flag', 'info', ProductionOrderResource::getUrl('index', ['filters' => ['priority' => ['value' => 'top']]], panel: self::PANEL), 'Lihat daftar order'),
            default => null,
        };
    }

    /**
     * The notifications that follow a new calculation: late orders (N5), outdated values (N6), orders newly held (N8),
     * orders newly escalated (N9), and a full Prioritas Utama list (N10).
     *
     * @param  array<int, int>  $heldBefore  ids of the orders that were held before this calculation
     * @param  array<int, int>  $heldNow  ids of the orders that are held after it
     * @param  int  $staleOrders  number of complete orders that have an outdated manual value
     */
    public function afterCalculation(Evaluation $evaluation, array $heldBefore, array $heldNow, int $staleOrders): void
    {
        $managers = $this->usersWithRole(UserRole::Manager);
        $deciders = $this->usersWithRole(UserRole::Manager, UserRole::Leader);
        $url = $this->evaluationUrl($evaluation);

        $late = ProductionOrder::query()->late()->count();

        if ($late > 0) {
            $this->send('N5', $managers, 'Ada order terlambat', $late.' order sudah melewati tenggat saat '.$evaluation->name.' dihitung.', 'heroicon-o-exclamation-triangle', 'danger', ProductionOrderResource::getUrl('index', ['filters' => ['late' => ['isActive' => true]]], panel: self::PANEL), 'Lihat order terlambat');
        }

        if ($staleOrders > 0) {
            $this->send('N6', $managers, 'Nilai penilaian perlu diperbarui', $staleOrders.' order memiliki nilai manual yang sudah kedaluwarsa. Perbarui di Penilaian Order supaya hasil lebih tepat.', 'heroicon-o-clock', 'warning', AssessmentMatrix::getUrl(['filters' => ['stale' => ['isActive' => true]]], panel: self::PANEL), 'Buka Penilaian Order');
        }

        $newlyHeld = array_values(array_diff($heldNow, $heldBefore));

        if ($newlyHeld !== []) {
            $this->send('N8', $deciders, 'Ada order Tertahan', count($newlyHeld).' order Antri berada di luar daftar prioritas terlalu lama: '.$this->numbers($newlyHeld).'.', 'heroicon-o-pause-circle', 'warning', ProductionOrderResource::getUrl('index', ['filters' => ['held' => ['isActive' => true]]], panel: self::PANEL), 'Lihat order Tertahan');
        }

        $newlyEscalated = $this->newlyEscalated($evaluation);

        if ($newlyEscalated !== []) {
            $this->send('N9', $deciders, 'Order masuk kelompok eskalasi', count($newlyEscalated).' order menunggu lebih dari '.EvaluationSettings::maxWaitDays().' hari dan dinaikkan: '.$this->numbers($newlyEscalated).'.', 'heroicon-o-arrow-trending-up', 'danger', $url, 'Buka evaluasi');
        }

        if (app(EvaluationWorkflow::class)->planFor($evaluation)->isFull()) {
            $this->send('N10', $deciders, 'Slot Prioritas Utama penuh', 'Order terkunci dan dieskalasi sudah memakai semua '.($evaluation->top_n ?? EvaluationSettings::topN()).' slot, jadi pesanan baru tidak kebagian label. Jumlah prioritas perlu ditinjau.', 'heroicon-o-exclamation-circle', 'warning', $url, 'Buka evaluasi');
        }
    }

    /**
     * A scheduled calculation that failed or found no active order (N7), for the admin and the manager.
     */
    public function scheduledProblem(string $reason): void
    {
        $this->send('N7', $this->usersWithRole(UserRole::Admin, UserRole::Manager), 'Perhitungan terjadwal tidak berjalan', $reason, 'heroicon-o-exclamation-triangle', 'danger', EvaluationResource::getUrl('index', panel: self::PANEL), 'Buka Hasil Evaluasi');
    }

    /**
     * Orders in the escalation group of this evaluation that were not in it in the evaluation before.
     *
     * @return array<int, int>
     */
    private function newlyEscalated(Evaluation $evaluation): array
    {
        $current = EvaluationItem::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('rank_group', RankGroup::Escalated->value)
            ->pluck('production_order_id');

        if ($current->isEmpty()) {
            return [];
        }

        $previous = Evaluation::query()
            ->where('id', '<', $evaluation->id)
            ->where('status', '!=', 'canceled')
            ->orderByDesc('id')
            ->value('id');

        $before = $previous === null
            ? collect()
            : EvaluationItem::query()->where('evaluation_id', $previous)->where('rank_group', RankGroup::Escalated->value)->pluck('production_order_id');

        return ProductionOrder::query()
            ->where('status', OrderStatus::Queued->value)
            ->whereIn('id', $current->diff($before))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(string $code, Collection $recipients, string $title, string $body, string $icon, string $color, string $url, string $button): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color)
            ->viewData(['code' => $code])
            ->actions([
                Action::make('open')->label($button)->url($url)->button()->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRole(UserRole ...$roles): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersById(?int $id): Collection
    {
        return User::query()->where('is_active', true)->whereKey($id)->get();
    }

    private function evaluationUrl(Evaluation $evaluation): string
    {
        return EvaluationResource::getUrl('view', ['record' => $evaluation], panel: self::PANEL);
    }

    /**
     * The order numbers for a message, the first five with "dan n lainnya" after them.
     *
     * @param  array<int, int>  $ids
     */
    private function numbers(array $ids): string
    {
        $numbers = ProductionOrder::query()->whereIn('id', $ids)->orderBy('id')->pluck('number')->all();
        $shown = array_slice($numbers, 0, 5);
        $more = count($numbers) - count($shown);

        return implode(', ', $shown).($more > 0 ? ' dan '.$more.' lainnya' : '');
    }
}
