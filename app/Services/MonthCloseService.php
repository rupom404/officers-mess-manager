<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthCloseService
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Preview closing data before locking.
     */
    public function preview(?int $messId = null, ?int $year = null, ?int $month = null): array
    {
        $messId = $messId ?? Mess::activeId() ?? 1;
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        try {
            $report = $this->reportService->monthlyReport($messId, $year, $month);
            return [
                'total_bazar' => (float) ($report['total_bazar'] ?? 0),
                'total_meals' => (float) ($report['total_meals'] ?? 0),
                'meal_rate'   => (float) ($report['meal_rate'] ?? 0),
                'total_fixed' => (float) ($report['total_fixed'] ?? 0),
                'members'     => $report['members'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('MonthCloseService preview error: ' . $e->getMessage());
            return [
                'total_bazar' => 0.0,
                'total_meals' => 0.0,
                'meal_rate'   => 0.0,
                'total_fixed' => 0.0,
                'members'     => [],
            ];
        }
    }

    /**
     * Execute closing, write immutable snapshots, and forward member balances.
     */
    public function close(int $messId, int $year, int $month, ?int $userId = null): MonthlyClosing
    {
        $userId = $userId ?? auth()->id() ?? 1;

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

            // 2. Fetch official report data
            $report = $this->reportService->monthlyReport($messId, $year, $month);

            $totalBazar = (float) ($report['total_bazar'] ?? 0);
            $totalMeals = (float) ($report['total_meals'] ?? 0);
            $mealRate   = (float) ($report['meal_rate'] ?? 0);
            $totalFixed = (float) ($report['total_fixed'] ?? 0);
            $members    = $report['members'] ?? [];

            // 3. Create MonthlyClosing record
            $closing = MonthlyClosing::create([
                'mess_id'     => $messId,
                'year'        => $year,
                'month'       => $month,
                'total_bazar' => $totalBazar,
                'total_meals' => $totalMeals,
                'meal_rate'   => $mealRate,
                'total_fixed' => $totalFixed,
                'closed_by'   => $userId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // 4. Save Member Summaries & Forward Ending Balances to Next Month
            foreach ($members as $m) {
                $memberId = $m['id'] ?? $m['member_id'] ?? null;
                if (! $memberId) {
                    continue;
                }

                $meals = (float) ($m['meals'] ?? 0);
                $mealCost = (float) ($m['meal_cost'] ?? $m['bill'] ?? 0);
                $paid = (float) ($m['paid'] ?? $m['bill_payments'] ?? 0);
                $broughtForward = (float) ($m['brought_forward'] ?? 0);

                // Live closing balance: positive = credit, negative = owes
                if (isset($m['closing_net']) && is_numeric($m['closing_net'])) {
                    $closingBalance = (float) $m['closing_net'];
                } else {
                    $closingBalance = ($paid + $broughtForward) - $mealCost;
                }

                MonthlyMemberSummary::create([
                    'monthly_closing_id' => $closing->id,
                    'member_id'          => $memberId,
                    'meals'              => $meals,
                    'meal_cost'          => $mealCost,
                    'bill_payments'      => $paid,
                    'brought_forward'    => $broughtForward,
                    'closing_balance'    => $closingBalance,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // AUTOMATIC FORWARDING: Carry forward ending balance to member's opening balance
                Member::query()
                    ->where('id', $memberId)
                    ->update([
                        'opening_balance' => $closingBalance,
                    ]);
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

    public function execute(int $messId, int $year, int $month, ?int $userId = null): MonthlyClosing
    {
        return $this->close($messId, $year, $month, $userId);
    }

    public function closeMonth(int $messId, int $year, int $month, ?int $userId = null): MonthlyClosing
    {
        return $this->close($messId, $year, $month, $userId);
    }
}