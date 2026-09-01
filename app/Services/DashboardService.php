<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\ExpenseKind;
use App\Support\MealOffStatus;
use App\Support\MealType;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function __construct(
        private readonly BillPreviewService $preview,
        private readonly ChartBucketingService $bucketing = new ChartBucketingService,
    ) {}

    public function pendingMealOffCount(): int
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return 0;
        }

        return (int) MealOffRequest::query()
            ->where('mess_id', $messId)
            ->where('status', MealOffStatus::PENDING)
            ->count();
    }

    public function managerCards(): array
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return [
                'total_members' => 0,
                'today_meals' => 0.0,
                'total_meals' => 0.0,
                'monthly_expenses' => 0.0,
                'meal_rate' => 0.0,
                'total_credit' => 0.0,
                'total_dues' => 0.0,
                'total_member_balance' => 0.0,
            ];
        }

        $now = now();
        $key = $this->countsCacheKey($messId, $now);

        $counts = Cache::remember($key, now()->addHour(), function () use ($messId, $now) {
            return [
                'total_members' => (int) Member::query()
                    ->where('mess_id', $messId)
                    ->where('status', MemberStatus::ACTIVE)
                    ->count(),
                'today_meals' => $this->todayMealTotal($messId, $now),
                'monthly_expenses' => (float) Expense::query()
                    ->where('mess_id', $messId)
                    ->whereBetween('date', [
                        $now->copy()->startOfMonth()->toDateString(),
                        $now->copy()->endOfMonth()->toDateString(),
                    ])
                    ->sum('amount'),
            ];
        });

        $preview = $this->preview->preview($now->year, $now->month);
        $members = $preview['members'] ?? [];

        $netByMember = collect($members)->map(
            fn ($m) => (float) ($m['advance_balance'] ?? 0) - (float) ($m['due_balance'] ?? 0)
        );

        $totalCredit = (float) $netByMember->sum(fn ($net) => max(0.0, $net));
        $totalDues = (float) $netByMember->sum(fn ($net) => abs(min(0.0, $net)));

        return [
            'total_members' => $counts['total_members'],
            'today_meals' => $counts['today_meals'],
            'total_meals' => (float) ($preview['total_meals'] ?? 0.0),
            'monthly_expenses' => $counts['monthly_expenses'],
            'meal_rate' => (float) ($preview['meal_rate'] ?? 0.0),
            'total_credit' => $totalCredit,
            'total_dues' => $totalDues,
            'total_member_balance' => round($totalCredit - $totalDues, 2),
        ];
    }

    public function mealTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $b = MealType::value(MealType::BREAKFAST);
        $l = MealType::value(MealType::LUNCH);
        $d = MealType::value(MealType::DINNER);
        $bucket = $this->bucketing->bucket($from, $to);

        $rows = MealEntry::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'date, '
                ."SUM((CASE WHEN breakfast THEN {$b} ELSE 0 END) "
                ."+ (CASE WHEN lunch THEN {$l} ELSE 0 END) "
                ."+ (CASE WHEN dinner THEN {$d} ELSE 0 END)) AS total"
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $guestRows = collect();
        if (class_exists(GuestMeal::class) && Schema::hasTable('guest_meals')) {
            $cols = Schema::getColumnListing('guest_meals');
            $col = in_array('count', $cols) ? 'count' : (in_array('meals', $cols) ? 'meals' : 'charge_amount');
            
            $guestRows = GuestMeal::query()
                ->whereHas('member', fn($q) => $q->where('mess_id', $messId))
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("date, SUM({$col}) as total")
                ->groupBy('date')
                ->get()
                ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        }

        $out = $this->fillBucketAxis($from, $to, $bucket['granularity'], function (string $dateKey) use ($rows, $guestRows) {
            $reg = $rows[$dateKey] ?? null;
            $gst = $guestRows[$dateKey] ?? null;
            return ((float) ($reg ? $reg->total : 0.0)) + ((float) ($gst ? $gst->total : 0.0));
        });

        return $out;
    }

    public function expenseTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $bazarCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::BAZAR)
            ->pluck('id')
            ->all();

        if (empty($bazarCategoryIds)) {
            return $this->emptySeries($from, $to);
        }

        $bucket = $this->bucketing->bucket($from, $to);
        $rows = $this->trendRows(
            Expense::query()
                ->where('mess_id', $messId)
                ->whereIn('expense_category_id', $bazarCategoryIds),
            $from,
            $to,
            $bucket['granularity'],
            'amount'
        );

        return $rows;
    }

    public function paymentTrend(int $messId, Carbon $from, Carbon $to): array
    {
        $bucket = $this->bucketing->bucket($from, $to);

        return $this->trendRows(
            Payment::query()->where('mess_id', $messId),
            $from,
            $to,
            $bucket['granularity'],
            'amount'
        );
    }

    private function todayMealTotal(int $messId, Carbon $date): float
    {
        $b = MealType::value(MealType::BREAKFAST);
        $l = MealType::value(MealType::LUNCH);
        $d = MealType::value(MealType::DINNER);

        $regMeals = (float) MealEntry::query()
            ->where('mess_id', $messId)
            ->where('date', $date->toDateString())
            ->selectRaw(
                "SUM((CASE WHEN breakfast THEN {$b} ELSE 0 END) "
                ."+ (CASE WHEN lunch THEN {$l} ELSE 0 END) "
                ."+ (CASE WHEN dinner THEN {$d} ELSE 0 END)) AS total"
            )
            ->value('total') ?? 0.0;

        $guestMeals = 0.0;
        if (class_exists(GuestMeal::class) && Schema::hasTable('guest_meals')) {
            $cols = Schema::getColumnListing('guest_meals');
            $col = in_array('count', $cols) ? 'count' : (in_array('meals', $cols) ? 'meals' : 'charge_amount');
            
            $guestMeals = (float) GuestMeal::query()
                ->whereHas('member', fn($q) => $q->where('mess_id', $messId))
                ->where('date', $date->toDateString())
                ->sum($col);
        }

        return $regMeals + $guestMeals;
    }

    private function trendRows($query, Carbon $from, Carbon $to, string $granularity, string $sumColumn): array
    {
        [$groupExpr, $orderExpr] = match ($granularity) {
            'day' => [DB::raw('date'), DB::raw('date')],
            'week' => [DB::raw("DATE_FORMAT(date, '%x-W%v')"), DB::raw("DATE_FORMAT(date, '%x-W%v')")],
            default => [DB::raw("DATE_FORMAT(date, '%Y-%m')"), DB::raw("DATE_FORMAT(date, '%Y-%m')")],
        };

        $rows = $query
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                (string) ($granularity === 'day' ? 'date' : $groupExpr->getValue(DB::connection()->getQueryGrammar())).' AS period, '
                ."SUM({$sumColumn}) AS total"
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        return $this->fillBucketAxis($from, $to, $granularity, function (string $bucketKey) use ($rows) {
            $row = $rows[$bucketKey] ?? null;

            return $row ? (float) $row->total : 0.0;
        });
    }

    private function fillBucketAxis(Carbon $from, Carbon $to, string $granularity, \Closure $lookup): array
    {
        $labels = [];
        $values = [];
        $cursor = $from->copy();

        while ($cursor <= $to) {
            $key = match ($granularity) {
                'day' => $cursor->toDateString(),
                'week' => $cursor->format('o-\WW'),
                default => $cursor->format('Y-m'),
            };

            $label = match ($granularity) {
                'day' => $cursor->translatedFormat('d M'),
                'week' => $cursor->translatedFormat('d M'),
                default => $cursor->translatedFormat('M Y'),
            };

            $labels[] = $label;
            $values[] = (float) $lookup($key);

            match ($granularity) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonth(),
            };
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function emptySeries(Carbon $from, Carbon $to): array
    {
        $bucket = $this->bucketing->bucket($from, $to);

        return $this->fillBucketAxis($from, $to, $bucket['granularity'], fn () => 0.0);
    }

    public function countsCacheKey(int $messId, Carbon $date): string
    {
        return "dash:counts:{$messId}:{$date->year}-".str_pad((string) $date->month, 2, '0', STR_PAD_LEFT);
    }

    public function membersWithDues(?int $messId = null): array
    {
        $messId = $messId ?? \App\Models\Mess::activeId();
        if (! $messId) {
            return [];
        }

        try {
            $now = \Carbon\Carbon::now();
            $year = $now->year;
            $month = $now->month;

            $preview = $this->preview->preview($year, $month);
            $members = collect($preview['members'] ?? [])->filter(function($m) {
                return ($m['status'] ?? 'active') === 'active';
            });

            $dueMembers = [];
            foreach ($members as $pm) {
                $netBalance = $pm['closing_net'] ?? (($pm['bill_payments'] + $pm['brought_forward']) - $pm['meal_cost']);
                if ($netBalance < -0.05) {
                    $dueMembers[] = [
                        'id'   => $pm['member_id'],
                        'name' => $pm['name'],
                        'net'  => abs($netBalance),
                    ];
                }
            }

            usort($dueMembers, fn ($a, $b) => $b['net'] <=> $a['net']);
            return $dueMembers;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('membersWithDues error: ' . $e->getMessage());
            return [];
        }
    }

    public function bazarVsCollection(int $messId): array
    {
        $now = now();
        $preview = $this->preview->preview($now->year, $now->month);
        $spend = (float) (($preview['total_bazar'] ?? 0) + ($preview['total_fixed'] ?? 0));
        $collected = (float) Payment::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->sum('amount');

        return ['spend' => $spend, 'collected' => $collected];
    }

    public function expenseCategoryMix(int $messId): array
    {
        $now = now();
        $rows = Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->with('category:id,name')
            ->get();

        $grouped = [];
        foreach ($rows as $expense) {
            $label = $expense->category?->name ?? __('Uncategorized');
            $grouped[$label] = ($grouped[$label] ?? 0) + (float) $expense->amount;
        }
        arsort($grouped);

        $out = [];
        foreach ($grouped as $label => $amount) {
            $out[] = ['label' => $label, 'amount' => (float) $amount];
        }

        return $out;
    }

    public function topEaters(int $messId): array
    {
        $now = now();
        $preview = $this->preview->preview($now->year, $now->month);

        return collect($preview['members'] ?? [])
            ->sortByDesc('meals')
            ->take(5)
            ->values()
            ->map(fn (array $m) => ['id' => $m['member_id'], 'name' => $m['name'], 'meals' => (float) $m['meals']])
            ->all();
    }
}