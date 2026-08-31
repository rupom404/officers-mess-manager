<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthCloseService
{
    public function __construct(
        protected BillPreviewService $billPreview,
    ) {}

    /**
     * Preview the exact figures shown on the live Monthly Report.
     *
     * Closing must use the canonical BillPreviewService so there is only one
     * calculation engine for an open month. The cache is explicitly invalidated
     * before both preview and close to prevent stale payment/meal values from
     * being frozen into the snapshot.
     */
    public function preview(?int $messId = null, ?int $year = null, ?int $month = null): array
    {
        $messId = $messId ?? Mess::activeId() ?? 1;
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return $this->freshReport($year, $month);
    }

    /**
     * Freeze the exact open-month Monthly Report values into an immutable snapshot.
     *
     * Accounting rule:
     *   closing_balance = brought_forward + advance_payments
     *                    + bill_payments - gross_bill
     *
     * `advance_applied` is a legacy snapshot column name and stores the
     * bill-payment amount for compatibility with ReportService.
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

            // Always invalidate first: payments/meals can change while the
            // report cache is still warm. A close must never freeze stale data.
            $report = $this->freshReport($year, $month);

            $closing = new MonthlyClosing();
            $closing->mess_id = $messId;
            $closing->year = $year;
            $closing->month = $month;
            $closing->total_bazar = round((float) ($report['total_bazar'] ?? 0), 2);
            $closing->total_fixed_expense = round((float) ($report['total_fixed'] ?? 0), 2);
            $closing->total_meals = round((float) ($report['total_meals'] ?? 0), 2);
            $closing->meal_rate = round((float) ($report['meal_rate'] ?? 0), 4);
            $closing->member_count = count($report['members'] ?? []);
            $closing->closed_at = now();
            $closing->closed_by = $userId;
            $closing->status = 'closed';
            $closing->save();

            foreach (($report['members'] ?? []) as $member) {
                $memberId = (int) ($member['member_id'] ?? 0);
                if ($memberId <= 0) {
                    continue;
                }

                $meals = (float) ($member['meals'] ?? 0);
                $mealCost = (float) ($member['meal_cost'] ?? 0);
                $fixedShare = (float) ($member['fixed_share'] ?? 0);
                $guestCharge = (float) ($member['guest_total'] ?? 0);
                $grossBill = (float) ($member['bill'] ?? ($mealCost + $fixedShare + $guestCharge));
                $billPayments = (float) ($member['bill_payments'] ?? 0);
                $advancePayments = (float) ($member['advance_payments'] ?? 0);
                $broughtForward = (float) ($member['brought_forward'] ?? 0);
                $due = (float) ($member['due'] ?? 0);

                $closingBalance = round(
                    $broughtForward + $advancePayments + $billPayments - $grossBill,
                    2
                );

                $summary = new MonthlyMemberSummary();
                $summary->mess_id = $messId;
                $summary->monthly_closing_id = $closing->id;
                $summary->member_id = $memberId;
                $summary->total_meals = round($meals, 2);
                $summary->meal_rate = round((float) ($report['meal_rate'] ?? 0), 4);
                $summary->meal_cost = round($mealCost, 2);
                $summary->fixed_cost_share = round($fixedShare, 2);
                $summary->guest_meal_charge = round($guestCharge, 2);
                $summary->gross_bill = round($grossBill, 2);

                // Legacy name: this column is surfaced by ReportService as
                // `bill_payments` and therefore must equal this month's bill payments.
                $summary->advance_applied = round($billPayments, 2);
                $summary->net_bill = round($grossBill - $billPayments, 2);
                $summary->payments_received = round($billPayments, 2);
                $summary->balance_due = round($due, 2);

                if (\Illuminate\Support\Facades\Schema::hasColumn('monthly_member_summaries', 'brought_forward')) {
                    $summary->brought_forward = round($broughtForward, 2);
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('monthly_member_summaries', 'closing_balance')) {
                    $summary->closing_balance = $closingBalance;
                }

                $summary->save();

                // Carry the frozen signed closing position into the running balance
                // used as next month's brought-forward value.
                $balance = AdvanceBalance::query()
                    ->where('member_id', $memberId)
                    ->first();

                if ($closingBalance >= 0) {
                    if ($balance) {
                        $balance->balance = number_format($closingBalance, 2, '.', '');
                        $balance->due_balance = '0.00';
                        $balance->last_updated_at = now();
                        $balance->save();
                    } else {
                        AdvanceBalance::create([
                            'mess_id' => $messId,
                            'member_id' => $memberId,
                            'balance' => number_format($closingBalance, 2, '.', ''),
                            'due_balance' => '0.00',
                            'last_updated_at' => now(),
                        ]);
                    }
                } else {
                    if ($balance) {
                        $balance->balance = '0.00';
                        $balance->due_balance = number_format(abs($closingBalance), 2, '.', '');
                        $balance->last_updated_at = now();
                        $balance->save();
                    } else {
                        AdvanceBalance::create([
                            'mess_id' => $messId,
                            'member_id' => $memberId,
                            'balance' => '0.00',
                            'due_balance' => number_format(abs($closingBalance), 2, '.', ''),
                            'last_updated_at' => now(),
                        ]);
                    }
                }
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
     * Get a fresh canonical live report. Closed months never reach this method
     * from MonthCloseController because closing is only allowed once.
     */
    protected function freshReport(int $year, int $month): array
    {
        $this->billPreview->invalidate($year, $month);
        return $this->billPreview->preview($year, $month);
    }
}
