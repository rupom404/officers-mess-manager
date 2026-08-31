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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MonthCloseService
{
    public function __construct(
        protected ?ReportService $reportService = null
    ) {}

    /**
     * Preview the figures that will be frozen by month close.
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
            'meal_rate' => (float) ($report['meal_rate'] ?? 0),
            'total_fixed' => (float) ($report['total_fixed'] ?? $report['fixed'] ?? 0),
            'members' => $report['members'] ?? [],
        ];
    }

    /**
     * Execute month closing atomically, persist snapshots, and carry forward balances.
     */
    public function close(int $messId, int $year, int $month, ?int $userId = null): MonthlyClosing
    {
        $userId = $userId ?? auth()->id() ?? User::first()?->id ?? null;

        return DB::transaction(function () use ($messId, $year, $month, $userId) {
            $existing = MonthlyClosing::query()
                ->where('mess_id', $messId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                return $existing;
            }

            $report = $this->resolveReportData($messId, $year, $month);

            $totalBazar = (float) ($report['total_bazar'] ?? $report['bazar'] ?? 0);
            $totalMeals = (float) ($report['total_meals'] ?? $report['meals'] ?? 0);
            $mealRate = (float) ($report['meal_rate'] ?? ($totalMeals > 0 ? $totalBazar / $totalMeals : 0));
            $totalFixed = (float) ($report['total_fixed'] ?? $report['fixed'] ?? 0);
            $members = $report['members'] ?? [];

            $closingColumns = Schema::hasTable('monthly_closings')
                ? Schema::getColumnListing('monthly_closings')
                : [];

            $closingData = $this->onlyExistingColumns($closingColumns, [
                'mess_id' => $messId,
                'year' => $year,
                'month' => $month,
                'total_bazar' => $totalBazar,
                'bazar_total' => $totalBazar,
                'total_expenses' => $totalBazar,
                'total_meals' => $totalMeals,
                'meals_total' => $totalMeals,
                'meal_rate' => $mealRate,
                'rate' => $mealRate,
                'total_fixed' => $totalFixed,
                'fixed_total' => $totalFixed,
                'closed_by' => $userId,
                'user_id' => $userId,
                'status' => 'closed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            MonthlyClosing::unguard();
            $closing = MonthlyClosing::create($closingData);
            MonthlyClosing::reguard();

            $summaryColumns = Schema::hasTable('monthly_member_summaries')
                ? Schema::getColumnListing('monthly_member_summaries')
                : [];

            foreach ($members as $member) {
                $memberId = $member['id'] ?? $member['member_id'] ?? null;
                if (! $memberId) {
                    continue;
                }

                $meals = (float) ($member['meals'] ?? 0);
                $mealCost = (float) ($member['meal_cost'] ?? $member['bill'] ?? 0);
                $paid = (float) ($member['bill_payments'] ?? $member['paid'] ?? 0);
                $broughtForward = (float) ($member['brought_forward'] ?? 0);

                $closingBalance = isset($member['closing_net']) && is_numeric($member['closing_net'])
                    ? (float) $member['closing_net']
                    : ($paid + $broughtForward) - $mealCost;

                $summaryData = $this->onlyExistingColumns($summaryColumns, [
                    'monthly_closing_id' => $closing->id,
                    'closing_id' => $closing->id,
                    'member_id' => $memberId,
                    'meals' => $meals,
                    'meal_count' => $meals,
                    'meal_cost' => $mealCost,
                    'bill' => $mealCost,
                    'bill_payments' => $paid,
                    'paid' => $paid,
                    'brought_forward' => $broughtForward,
                    'opening_balance' => $broughtForward,
                    'closing_balance' => $closingBalance,
                    'closing_net' => $closingBalance,
                    'net_balance' => $closingBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                MonthlyMemberSummary::unguard();
                MonthlyMemberSummary::create($summaryData);
                MonthlyMemberSummary::reguard();

                if (Schema::hasColumn('members', 'opening_balance')) {
                    Member::withoutGlobalScopes()
                        ->whereKey($memberId)
                        ->update(['opening_balance' => $closingBalance]);
                }
            }

            // Notifications are best-effort and must never make a successful close fail.
            try {
                if (class_exists(\App\Services\NotificationService::class)) {
                    $notificationService = app(\App\Services\NotificationService::class);
                    if (method_exists($notificationService, 'notifyMonthClosed')) {
                        $notificationService->notifyMonthClosed($closing);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Month close notification skipped: '.$e->getMessage());
            }

            return $closing;
        });
    }

    /**
     * Use the canonical report calculation first. Only use the compatibility
     * fallback when the report service is unavailable or throws an exception.
     */
    protected function resolveReportData(int $messId, int $year, int $month): array
    {
        if ($this->reportService && method_exists($this->reportService, 'monthlyReport')) {
            try {
                $report = $this->reportService->monthlyReport($year, $month);

                if (is_array($report) && array_key_exists('members', $report)) {
                    return $report;
                }
            } catch (\Throwable $e) {
                Log::warning('Month close report service fallback: '.$e->getMessage(), [
                    'mess_id' => $messId,
                    'year' => $year,
                    'month' => $month,
                ]);
            }
        }

        return $this->computeDirectReport($messId, $year, $month);
    }

    /**
     * Compatibility fallback for deployments whose schema is older than the code.
     * IMPORTANT: this project's members table uses `status`, not `is_active`.
     */
    protected function computeDirectReport(int $messId, int $year, int $month): array
    {
        $totalBazar = (float) Expense::withoutGlobalScopes()
            ->where('mess_id', $messId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $membersQuery = Member::withoutGlobalScopes()
            ->where('mess_id', $messId);

        // Current members schema uses status='active'; `is_active` does not exist.
        if (Schema::hasColumn('members', 'status')) {
            $membersQuery->where('status', 'active');
        }

        $members = $membersQuery->get();

        $processedMembers = [];
        $totalMeals = 0.0;

        foreach ($members as $member) {
            $meals = 0.0;

            if (Schema::hasTable('meal_entries')) {
                $entries = MealEntry::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->get(['breakfast', 'lunch', 'dinner']);

                foreach ($entries as $entry) {
                    $meals += ($entry->breakfast ? 0.5 : 0)
                        + ($entry->lunch ? 1.0 : 0)
                        + ($entry->dinner ? 1.0 : 0);
                }
            }

            if (Schema::hasTable('guest_meals')) {
                $guestColumns = Schema::getColumnListing('guest_meals');
                $guestCountColumn = in_array('count', $guestColumns, true)
                    ? 'count'
                    : (in_array('meals', $guestColumns, true) ? 'meals' : null);

                if ($guestCountColumn) {
                    $meals += (float) GuestMeal::withoutGlobalScopes()
                        ->where('member_id', $member->id)
                        ->whereYear('date', $year)
                        ->whereMonth('date', $month)
                        ->sum($guestCountColumn);
                }
            }

            $paymentColumns = Schema::hasTable('payments')
                ? Schema::getColumnListing('payments')
                : [];
            $paymentDateColumn = in_array('date', $paymentColumns, true)
                ? 'date'
                : (in_array('paid_at', $paymentColumns, true) ? 'paid_at' : null);

            $paid = 0.0;
            if ($paymentDateColumn) {
                $paid = (float) Payment::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->whereYear($paymentDateColumn, $year)
                    ->whereMonth($paymentDateColumn, $month)
                    ->sum('amount');
            }

            $broughtForward = (float) ($member->opening_balance ?? 0);
            $totalMeals += $meals;

            $processedMembers[] = [
                'id' => $member->id,
                'member_id' => $member->id,
                'name' => $member->name,
                'meals' => $meals,
                'paid' => $paid,
                'bill_payments' => $paid,
                'brought_forward' => $broughtForward,
            ];
        }

        $mealRate = $totalMeals > 0 ? $totalBazar / $totalMeals : 0.0;

        foreach ($processedMembers as &$member) {
            $mealCost = $member['meals'] * $mealRate;
            $member['meal_cost'] = $mealCost;
            $member['bill'] = $mealCost;
            $member['closing_net'] = ($member['paid'] + $member['brought_forward']) - $mealCost;
        }
        unset($member);

        return [
            'total_bazar' => $totalBazar,
            'total_meals' => $totalMeals,
            'meal_rate' => $mealRate,
            'total_fixed' => 0.0,
            'members' => $processedMembers,
        ];
    }

    /**
     * Keep the dynamic-schema protection used by the production deployment.
     */
    private function onlyExistingColumns(array $columns, array $values): array
    {
        if (empty($columns)) {
            return $values;
        }

        $result = [];
        foreach ($values as $column => $value) {
            if (in_array($column, $columns, true)) {
                $result[$column] = $value;
            }
        }

        return $result;
    }
}
