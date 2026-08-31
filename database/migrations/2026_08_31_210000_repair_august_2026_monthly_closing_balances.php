<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair the first locked closing created before brought-forward values
     * were correctly frozen into the snapshot.
     *
     * This is intentionally scoped to the August 2026 closing only. The repair
     * derives the opening signed position from the member's running
     * advance/due balances and August advance deposits, then rebuilds the
     * frozen closing balance from the same Monthly Report equation:
     *
     * closing = brought_forward + August advance deposits
     *           + August bill payments - gross bill
     *
     * No live meal/bill amounts are rewritten.
     */
    public function up(): void
    {
        if (! Schema::hasTable('monthly_closings') || ! Schema::hasTable('monthly_member_summaries')) {
            return;
        }

        if (! Schema::hasColumn('monthly_member_summaries', 'brought_forward')
            || ! Schema::hasColumn('monthly_member_summaries', 'closing_balance')) {
            return;
        }

        $closing = DB::table('monthly_closings')
            ->where('year', 2026)
            ->where('month', 8)
            ->where('status', 'closed')
            ->orderByDesc('id')
            ->first();

        if (! $closing) {
            return;
        }

        $paymentTypeColumn = Schema::hasColumn('payments', 'type') ? 'type' : null;
        $hasAdvanceBalance = Schema::hasTable('advance_balances')
            && Schema::hasColumn('advance_balances', 'balance')
            && Schema::hasColumn('advance_balances', 'due_balance');

        DB::transaction(function () use ($closing, $paymentTypeColumn, $hasAdvanceBalance) {
            $summaries = DB::table('monthly_member_summaries')
                ->where('monthly_closing_id', $closing->id)
                ->get();

            foreach ($summaries as $summary) {
                $memberId = (int) $summary->member_id;

                $advancePayments = 0.0;
                $billPayments = 0.0;

                if (Schema::hasTable('payments')) {
                    $payments = DB::table('payments')
                        ->where('member_id', $memberId)
                        ->whereYear('date', 2026)
                        ->whereMonth('date', 8)
                        ->get(['amount', ...($paymentTypeColumn ? ['type'] : [])]);

                    foreach ($payments as $payment) {
                        if ($paymentTypeColumn && (string) $payment->type === 'advance_deposit') {
                            $advancePayments += (float) $payment->amount;
                        } else {
                            $billPayments += (float) $payment->amount;
                        }
                    }
                }

                // Before this repair, the August snapshot stored a zero opening
                // position. The live running balances still represent that
                // opening position plus August advance deposits. Remove the
                // month's deposits to recover the opening net position.
                $currentNet = 0.0;
                if ($hasAdvanceBalance) {
                    $balance = DB::table('advance_balances')
                        ->where('member_id', $memberId)
                        ->first(['balance', 'due_balance']);

                    if ($balance) {
                        $currentNet = (float) $balance->balance - (float) $balance->due_balance;
                    }
                }

                $broughtForward = round($currentNet - $advancePayments, 2);

                // For this historical repair the stored gross_bill and monthly
                // bill payments are authoritative; reconstruct only the frozen
                // signed closing position and leave balance_due as the
                // this-month due shown by the report.
                $grossBill = (float) $summary->gross_bill;
                $closingBalance = round(
                    $broughtForward + $advancePayments + $billPayments - $grossBill,
                    2
                );

                DB::table('monthly_member_summaries')
                    ->where('id', $summary->id)
                    ->update([
                        'brought_forward' => $broughtForward,
                        'closing_balance' => $closingBalance,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('monthly_closings') || ! Schema::hasTable('monthly_member_summaries')) {
            return;
        }

        $closing = DB::table('monthly_closings')
            ->where('year', 2026)
            ->where('month', 8)
            ->where('status', 'closed')
            ->orderByDesc('id')
            ->first();

        if (! $closing) {
            return;
        }

        DB::table('monthly_member_summaries')
            ->where('monthly_closing_id', $closing->id)
            ->update([
                'brought_forward' => 0,
                'closing_balance' => DB::raw('balance_due'),
                'updated_at' => now(),
            ]);
    }
};
