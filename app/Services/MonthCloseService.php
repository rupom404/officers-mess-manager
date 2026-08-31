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
     * Execute month closing atomically and persist the immutable monthly snapshot.
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

            // These columns are required by the actual monthly_closings schema.
            // Do not use guessed aliases here: PostgreSQL enforces NOT NULL.
            $closing = new MonthlyClosing();
            $closing->mess_id = $messId;
            $closing->year = $year;
            $closing->month = $month;
            $closing->total_bazar = round($totalBazar, 2);
            $closing->total_fixed_expense = round($totalFixed, 2);
            $closing->total_meals = round($totalMeals, 2);
            $closing->meal_rate = round($mealRate, 4);
            $closing->member_count = count($members);
            $closing->closed_at = now();
            $closing->closed_by = $userId;
            $closing->status = 'closed';
            $closing->save();

            // Persist the exact fields expected by monthly_member_summaries.
            foreach ($members as $member) {
                $memberId = $member['member_id'] ?? $member['id'] ?? null;
                if (! $memberId) {
                    continue;
                }

                $meals = (float) ($member['meals'] ?? $member['total_meals'] ?? 0);
                $mealCost = (float) ($member['meal_cost'] ?? 0);
                $fixedShare = (float) ($member['fixed_share'] ?? $member['fixed_cost_share'] ?? 0);
                $guestCharge = (float) ($member['guest_total'] ?? $member['guest_meal_charge'] ?? 0);
                $grossBill = (float) ($member['bill'] ?? $member['gross_bill'] ?? ($mealCost + $fixedShare + $guestCharge));
                $billPayments = (float) ($member['bill_payments'] ?? $member['payments_received'] ?? 0);
                $advancePayments = (float) ($member['advance_payments'] ?? 0);
                $broughtForward = (float) ($member['brought_forward'] ?? 0);
                $due = array_key_exists('due', $member)
                    ? (float) $member['due']
                    : round(max(0, $grossBill - $billPayments), 2);

                // Historical schema name: advance_applied actually stores bill-payment-type payments.
                $advanceApplied = $billPayments;
                $netBill = round($grossBill - $advanceApplied, 2);

                // Final net position after this month's activity.
                $closingBalance = round(
                    $broughtForward + $advancePayments + $billPayments - $grossBill,
                    2
                );

                $summary = new MonthlyMemberSummary();
                $summary->mess_id = $messId;
                $summary->monthly_closing_id = $closing->id;
                $summary->member_id = $memberId;
                $summary->total_meals = round($meals, 2);
                $summary->meal_rate = round($mealRate, 4);
                $summary->meal_cost = round($mealCost, 2);
                $summary->fixed_cost_share = round($fixedShare, 2);
                $summary->guest_meal_charge = round($guestCharge, 2);
                $summary->gross_bill = round($grossBill, 2);
                $summary->advance_applied = round($advanceApplied, 2);
                $summary->net_bill = round($netBill, 2);
                $summary->payments_received = round($billPayments, 2);
                $summary->balance_due = round($due, 2);

                // closing_balance was added later and exists on the current model/schema.
                if (Schema::hasColumn('monthly_member_summaries', 'brought_forward')) {
                    $summary->brought_forward = round($broughtForward, 2);
                }
                if (Schema::hasColumn('monthly_member_summaries', 'closing_balance')) {
                    $summary->closing_balance = $closingBalance;
                }

                $summary->save();
            }

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
     * Use the canonical report calculation first. Fall back only when necessary.
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
     * Compatibility fallback for older deployments. The current members schema
     * uses status='active'/'former', not an is_active column.
     */
    protected function computeDirectReport(int $messId, int $year, int $month): array
    {
        $totalBazar = (float) Expense::withoutGlobalScopes()
            ->where('mess_id', $messId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $membersQuery = Member::withoutGlobalScopes()->where('mess_id', $messId);
        if (Schema::hasColumn('members', 'status')) {
            $membersQuery->whereIn('status', ['active', 'former']);
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

            $guestTotal = 0.0;
            if (Schema::hasTable('guest_meals') && in_array('charge_amount', Schema::getColumnListing('guest_meals'), true)) {
                $guestTotal = (float) GuestMeal::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->sum('charge_amount');
            }

            $paid = 0.0;
            if (Schema::hasTable('payments') && in_array('date', Schema::getColumnListing('payments'), true)) {
                $paid = (float) Payment::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->sum('amount');
            }

            $broughtForward = 0.0;
            $totalMeals += $meals;

            $processedMembers[] = [
                'id' => $member->id,
                'member_id' => $member->id,
                'name' => $member->name,
                'meals' => $meals,
                'guest_total' => $guestTotal,
                'paid' => $paid,
                'bill_payments' => $paid,
                'advance_payments' => 0.0,
                'brought_forward' => $broughtForward,
            ];
        }

        $mealRate = $totalMeals > 0 ? round($totalBazar / $totalMeals, 2) : 0.0;

        foreach ($processedMembers as &$member) {
            $mealCost = round($member['meals'] * $mealRate, 2);
            $grossBill = round($mealCost + $member['guest_total'], 2);
            $member['meal_cost'] = $mealCost;
            $member['fixed_share'] = 0.0;
            $member['bill'] = $grossBill;
            $member['due'] = round(max(0.0, $grossBill - $member['bill_payments']), 2);
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
}
