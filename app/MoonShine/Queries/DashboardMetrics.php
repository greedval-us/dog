<?php

namespace App\MoonShine\Queries;

use App\Models\AdminAuditLog;
use App\Models\CurrencyTransaction;
use App\Models\DogWorkShift;
use App\Models\ItemPurchase;
use App\Models\KennelPurchase;
use App\Models\Pet;
use App\Models\PetCareAction;
use App\Models\PetSkillLesson;
use App\Models\User;
use App\Models\WorkShift;
use App\Modules\Players\Enums\PlayerStatus;
use App\MoonShine\Enums\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardMetrics
{
    /**
     * @return array{totals: array<string, int>, period: array<string, int>, waiting: array<string, int>, economy: array<string, array{issued: int, spent: int}>, registrations: array<string, int>, activities: array<string, int>, recent: iterable<AdminAuditLog>, system: array<string, int|string>}
     */
    public function get(StaffRole $role, int $days): array
    {
        $now = CarbonImmutable::now();
        $from = $now->startOfDay()->subDays($days - 1);
        $base = [
            'totals' => [
                'players' => User::query()->count(),
                'blocked' => User::query()->where('status', PlayerStatus::Blocked)->count(),
                'pets' => Pet::query()->count(),
            ],
            'period' => ['registrations' => User::query()->whereBetween('created_at', [$from, $now])->count()],
            'waiting' => [], 'economy' => [], 'registrations' => [], 'activities' => [],
            'recent' => $role === StaffRole::Analyst ? [] : AdminAuditLog::query()->when($role === StaffRole::Moderator, fn (Builder $q): Builder => $q->where('target_type', User::class))->latest('id')->limit(8)->get(),
            'system' => [],
        ];

        if ($role === StaffRole::Moderator) {
            return $base;
        }

        $activity = CurrencyTransaction::query()->select('user_id')->whereNotNull('user_id')->whereBetween('created_at', [$from, $now])->toBase();
        foreach ([PetCareAction::class, DogWorkShift::class, PetSkillLesson::class, WorkShift::class] as $model) {
            $activity->union($model::query()->select('user_id')->whereNotNull('user_id')->whereBetween('created_at', [$from, $now])->toBase());
        }

        $base['period'] += [
            'active_players' => DB::query()->fromSub($activity, 'activity')->distinct()->count('user_id'),
            'purchases' => ItemPurchase::query()->whereBetween('created_at', [$from, $now])->count(),
            'shop_spent' => (int) ItemPurchase::query()->whereBetween('created_at', [$from, $now])->sum('price_paid'),
            'kennel_purchases' => KennelPurchase::query()->whereBetween('created_at', [$from, $now])->count(),
            'care' => PetCareAction::query()->whereBetween('created_at', [$from, $now])->count(),
            'shifts' => DogWorkShift::query()->whereBetween('created_at', [$from, $now])->count(),
        ];

        $base['waiting'] = [
            'ready_care' => PetCareAction::query()->whereNull('completed_at')->where('ends_at', '<=', $now)->count(),
            'ready_shifts' => DogWorkShift::query()->whereNull('completed_at')->where('ends_at', '<=', $now)->count(),
        ];

        foreach (['coins', 'gems'] as $currency) {
            $transactions = CurrencyTransaction::query()->where('currency', $currency)->whereBetween('created_at', [$from, $now]);
            $base['economy'][$currency] = [
                'issued' => (int) (clone $transactions)->where('amount', '>', 0)->sum('amount'),
                'spent' => -(int) (clone $transactions)->where('amount', '<', 0)->sum('amount'),
            ];
        }

        $registrations = User::query()->whereBetween('created_at', [$from, $now])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');

        for ($date = $from; $date->lessThanOrEqualTo($now); $date = $date->addDay()) {
            $base['registrations'][$date->toDateString()] = (int) ($registrations[$date->toDateString()] ?? 0);
        }

        $base['activities'] = PetCareAction::query()->whereBetween('created_at', [$from, $now])
            ->selectRaw('"group", COUNT(*) AS total')->groupBy('group')->pluck('total', 'group')->map(fn ($count): int => (int) $count)->all();

        if ($role === StaffRole::Administrator) {
            $base['system'] = [
                'environment' => app()->environment(), 'php' => PHP_VERSION, 'laravel' => app()->version(),
                'database' => DB::getDriverName(), 'queue' => (string) config('queue.default'),
                'pending_jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(),
            ];
        }

        return $base;
    }
}
