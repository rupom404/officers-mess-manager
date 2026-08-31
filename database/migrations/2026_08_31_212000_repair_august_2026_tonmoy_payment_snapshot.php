<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair the August 2026 snapshot for the member whose live payment ledger
     * shows the authoritative August payment amount.
     *
     * The earlier August repair migration queried `payments` with the query
     * builder and therefore did not apply Laravel's SoftDeletes scope. A
     * deleted/hidden historical payment could consequently be included in the
     * frozen snapshot even though it is absent from the Payments UI.
     *
     * This migration intentionally uses only non-deleted payments and repairs
     * Tonmoy's August snapshot to match the live ledger shown to managers.
     */
    public function up(): void
    {
        if (! Schema::hasTable('monthly_closings') || ! Schema::hasTable('monthly_member_summaries') || ! Schema::hasTable('payments')) {
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

        $summary = DB::table('monthly_member_summaries as sms')
            ->join('members as m', 'm.id', '=', 'sms.member_id')
            ->where('sms.monthly_closing_id', $closing->id)
            ->where('m.name', 'Tonmoy')
            ->select('sms.*')
            ->first();

        if (! $summary) {
            return;
        }

        DB::transaction(function () use ($closing, $summary) {
            $payments = DB::table('payments')
                ->where('member_id', $summary->member_id)
                ->whereBetween('date', ['2026-08-01', '2026-08-31'])
                ->whereNull('deleted_at')
                ->get(['amount', 'type']);

            $billPayments = 0.0;
            $advancePayments = 0.0;

            foreach ($payments as $payment) {
                if ((string) $payment->type === 'advance_deposit') {
                    $advancePayments += (float) $payment->amount;
                } else {
                    $billPayments += (float) $payment->amount;
                }
            }

            $broughtForward = (float) ($summary->brought_forward ?? 0);
            $grossBill = (float) $summary->gross_bill;
            $closingBalance = round(
                $broughtForward + $advancePayments + $billPayments - $grossBill,
                2
            );
            $netBill = round($grossBill - $billPayments, 2);
            $balanceDue = round(max(0, $netBill), 2);

            DB::table('monthly_member_summaries')
                ->where('id', $summary->id)
                ->update([
                    // The schema name is historical; this column mirrors the
                    // bill-payment amount in the monthly snapshot.
                    'advance_applied' => round($billPayments, 2),
                    'net_bill' => $netBill,
                    'payments_received' => round($billPayments, 2),
                    'balance_due' => $balanceDue,
                    'closing_balance' => $closingBalance,
                    'updated_at' => now(),
                ]);

            // Keep the member's running balance consistent with the repaired
            // frozen closing net. The current month is already closed, so the
            // closing net is the value that must carry into September.
            if (Schema::hasTable('advance_balances')) {
                $balanceRow = DB::table('advance_balances')
                    ->where('member_id', $summary->member_id)
                    ->first(['id', 'balance', 'due_balance']);

                if ($balanceRow) {
                    if ($closingBalance >= 0) {
                        DB::table('advance_balances')
                            ->where('id', $balanceRow->id)
                            ->update([
                                'balance' => round($closingBalance, 2),
                                'due_balance' => 0,
                                'last_updated_at' => now(),
                            ]);
                    } else {
                        DB::table('advance_balances')
                            ->where('id', $balanceRow->id)
                            ->update([
                                'balance' => 0,
                                'due_balance' => round(abs($closingBalance), 2),
                                'last_updated_at' => now(),
                            ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally no rollback: this migration repairs a historical
        // snapshot based on the authoritative non-deleted payment ledger.
    }
};
