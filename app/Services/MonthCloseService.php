<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Models\User;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MonthCloseService
{
    public function __construct(
        protected ?ReportService $reportService = null
    ) {}

    /**
     * Preview closing data before locking.
     */
    public function preview(?int $messId = null, ?int $year = null, ?int $month = null): array
    {
        $messId = $messId ?? Mess::activeId() ?? 1;
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $report = $this->resolveReportData($messId, $year, $month);

        return [
            'total_bazar' => (float) ($report['total_bazar'] ?? $report['bazar'] ?? 0),
            'total_meals' => (float) ($report['total_meals'] ?? $report['meals'] ?? 0),
            'meal_rate'   => (float) ($report['meal_rate'] ?? 0),
            'total_fixed' => (float) ($report['total_fixed'] ?? $report['fixed'] ?? 0),
            'members'     => $report['members'] ?? [],
        ];
    }

    /**
     * Execute closing, write snapshots, and roll forward balances to next month.
     */
    public function close(int $messId, int $year, int $month, ?int $userId = null): MonthlyClosing
    {
        $userId = $userId ?? auth()->id() ?? User::first()?->id ?? null;

        return DB::transaction(function () use ($messId, $year, $month, $userId) {
            // 1. Check if already closed
            $existing = MonthlyClosing::query()
                ->where('mess_id', $messId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                return $existing;
            }

            // 2. Resolve calculation figures
            $report = $this->resolveReportData($messId, $year, $month);

            $totalBazar = (float) ($report['total_bazar'] ?? $report['bazar'] ?? 0);
            $totalMeals = (float) ($report['total_meals'] ?? $report['meals'] ?? 0);
            $mealRate   = (float) ($report['meal_rate'] ?? ($totalMeals > 0 ? ($totalBazar / $totalMeals) : 0));
            $totalFixed = (float) ($report['total_fixed'] ?? $report['fixed'] ?? 0);
            $members    = $report['members'] ?? [];

            // 3. Create MonthlyClosing Snapshot dynamically based on real DB columns
            $closingCols = Schema::hasTable('monthly_closings') ? Schema::getColumnListing('monthly_closings') : [];
            $candidateClosing = [
                'mess_id'        => $messId,
                'year'           => $year,
                'month'          => $month,
                'total_bazar'    => $totalBazar,
                'bazar_total'    => $totalBazar,
                'total_expenses' => $totalBazar,
                'total_meals'    => $totalMeals,
                'meals_total'    => $totalMeals,
                'meal_rate'      => $mealRate,
                'rate'           => $mealRate,
                'total_fixed'    => $totalFixed,
                'fixed_total'    => $totalFixed,
                'closed_by'      => $userId,
                'user_id'        => $userId,
                'status'         => 'closed',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            $closingInsert = [];
            foreach ($candidateClosing as $col => $val) {
                if (in_array($col, $closingCols)) {
                    $closingInsert[$col] = $val;
                }
            }

            MonthlyClosing::unguard();
            $closing = MonthlyClosing::create($closingInsert);
            MonthlyClosing::reguard();

            // 4. Save Member Summaries & Roll Over Ending Balances
            $summaryCols = Schema::hasTable('monthly_member_summaries') ? Schema::getColumnListing('monthly_member_summaries') : [];

            foreach ($members as $m) {
                $memberId = $m['id'] ?? $m['member_id'] ?? null;
                if (! $memberId) {
                    continue;
                }

                $meals = (float) ($m['meals'] ?? 0);
                $mealCost = (float) ($m['meal_cost'] ?? $m['bill'] ?? 0);
                $paid = (float) ($m['bill_payments'] ?? $m['paid'] ?? 0);
                $broughtForward = (float) ($m['brought_forward'] ?? 0);

                if (isset($m['closing_net']) && is_numeric($m['closing_net'])) {
                    $closingBalance = (float) $m['closing_net'];
                } elseif (isset($m['closing']) && is_numeric($m['closing'])) {
                    $closingBalance = (float) $m['closing'];
                } else {
                    $closingBalance = ($paid + $broughtForward) - $mealCost;
                }

                $candidateSummary = [
                    'monthly_closing_id' => $closing->id,
                    'closing_id'         => $closing->id,
                    'member_id'          => $memberId,
                    'meals'              => $meals,
                    'meal_count'         => $meals,
                    'meal_cost'          => $mealCost,
                    'bill'               => $mealCost,
                    'bill_payments'      => $paid,
                    'paid'               => $paid,
                    'brought_forward'    => $broughtForward,
                    'opening_balance'    => $broughtForward,
                    'closing_balance'    => $closingBalance,
                    'closing_net'        => $closingBalance,
                    'net_balance'        => $closingBalance,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];

                $summaryInsert = [];
                foreach ($candidateSummary as $col => $val) {
                    if (in_array($col, $summaryCols)) {
                        $summaryInsert[$col] = $val;
                    }
                }

                MonthlyMemberSummary::unguard();
                MonthlyMemberSummary::create($summaryInsert);
                MonthlyMemberSummary::reguard();

                // AUTOMATIC FORWARDING: Carry forward ending balance into member opening balance
                if (Schema::hasColumn('members', 'opening_balance')) {
                    Member::withoutGlobalScopes()
                        ->where('id', $memberId)
                        ->update(['opening_balance' => $closingBalance]);
                }
            }

            // 5. Attempt notifications safely without failing the transaction
            try {
                if (class_exists(\App\Services\NotificationService::class)) {
                    $notificationService = app(\App\Services\NotificationService::class);
                    if (method_exists($notificationService, 'notifyMonthClosed')) {
                        $notificationService->notifyMonthClosed($closing);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Month close notification skipped: ' . $e->getMessage());
            }

            return $closing;
        });
    }

    /**
     * Resilient report resolver with database calculation fallback.
     */
    protected function resolveReportData(int $messId, int $year, int $month): array
    {
        // 1. Try Period instance if available
        if ($this->reportService && class_exists(Period::class)) {
            try {
                $period = null;
                if (method_exists(Period::class, 'fromMonth')) {
                    $period = Period::fromMonth($year, $month);
                } elseif (method_exists(Period::class, 'make')) {
                    $period = Period::make($year, $month);
                } else {
                    $period = new Period($year, $month);
                }

                if ($period && method_exists($this->reportService, 'monthlyReport')) {
                    $res = $this->reportService->monthlyReport($messId, $period);
                    if (! empty($res) && ! empty($res['members'])) {
                        return $res;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Period-based report service fallback: ' . $e->getMessage());
            }
        }

        // 2. Direct PostgreSQL Query Calculation
        return $this->computeDirectReport($messId, $year, $month);
    }

    protected function computeDirectReport(int $messId, int $year, int $month): array
    {
        $totalBazar = (float) Expense::withoutGlobalScopes()
            ->where('mess_id', $messId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        if ($totalBazar === 0.0) {
            $totalBazar = (float) Expense::withoutGlobalScopes()
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');
        }

        $members = Member::withoutGlobalScopes()
            ->where('mess_id', $messId)
            ->where('is_active', true)
            ->get();

        if ($members->isEmpty()) {
            $members = Member::withoutGlobalScopes()->where('is_active', true)->get();
        }

        $processedMembers = [];
        $totalMealsSum = 0;
        $entryCols = Schema::hasTable('meal_entries') ? Schema::getColumnListing('meal_entries') : [];

        foreach ($members as $member) {
            $meals = 0.0;
            if (class_exists(MealEntry::class) && Schema::hasTable('meal_entries')) {
                $query = MealEntry::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month);

                if (in_array('count', $entryCols)) {
                    $meals = (float) $query->sum('count');
                } elseif (in_array('total_meals', $entryCols)) {
                    $meals = (float) $query->sum('total_meals');
                } elseif (in_array('meals', $entryCols)) {
                    $meals = (float) $query->sum('meals');
                } else {
                    $entries = $query->get();
                    $meals = (float) $entries->sum(fn ($e) => ($e->breakfast ? 0.5 : 0) + ($e->lunch ? 1.0 : 0) + ($e->dinner ? 1.0 : 0));
                }
            }

            if (class_exists(GuestMeal::class) && Schema::hasTable('guest_meals')) {
                $guestCols = Schema::getColumnListing('guest_meals');
                $col = in_array('count', $guestCols) ? 'count' : (in_array('meals', $guestCols) ? 'meals' : null);
                if ($col) {
                    $guestCount = (float) GuestMeal::withoutGlobalScopes()
                        ->where('member_id', $member->id)
                        ->whereYear('date', $year)
                        ->whereMonth('date', $month)
                        ->sum($col);
                    $meals += $guestCount;
                }
            }

            $paidCols = Schema::hasTable('payments') ? Schema::getColumnListing('payments') : [];
            $dateCol = in_array('paid_at', $paidCols) ? 'paid_at' : 'date';

            $paid = (float) Payment::withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->whereYear($dateCol, $year)
                ->whereMonth($dateCol, $month)
                ->sum('amount');

            $broughtForward = (float) ($member->opening_balance ?? 0);
            $totalMealsSum += $meals;

            $processedMembers[] = [
                'id'              => $member->id,
                'member_id'       => $member->id,
                'name'            => $member->name,
                'meals'           => $meals,
                'paid'            => $paid,
                'bill_payments'   => $paid,
                'brought_forward' => $broughtForward,
            ];
        }

        $mealRate = $totalMealsSum > 0 ? ($totalBazar / $totalMealsSum) : 0.0;

        foreach ($processedMembers as &$pm) {
            $cost = $pm['meals'] * $mealRate;
            $pm['meal_cost'] = $cost;
            $pm['bill'] = $cost;
            $pm['closing_net'] = ($pm['paid'] + $pm['brought_forward']) - $cost;
        }

        return [
            'total_bazar' => $totalBazar,
            'total_meals' => $totalMealsSum,
            'meal_rate'   => $mealRate,
            'total_fixed' => 0.0,
            'members'     => $processedMembers,
        ];
    }
}