<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * ReportService — manager-side read-only reports.
 *
 * - monthlyReport(): D-26 switch between live compute (BillPreviewService)
 *   for open/never-closed months and the immutable MonthlyMemberSummary
 *   snapshot for closed months. Returns the SAME top-level shape as
 *   BillPreviewService::preview() so views can treat them interchangeably,
 *   plus a `source` key ('live' | 'snapshot') for the closed-month badge.
 *
 * - expenseReport() / paymentReport(): paginated direct queries with
 *   validated GET filters (D-18 sticky querystring).
 *
 * NOTE (CR-03 / Pitfall 3): the snapshot's `advance_applied` column is
 * misnamed — it holds bill-payment-type payments, not advance deposits
 * consumed. We never expose `advance_applied` to the view; we surface it
 * as `bill_payments` to mirror BillPreviewService's live shape.
 */
class ReportService
{
    public function __construct(
        private readonly BillPreviewService $preview,
    ) {}

    /**
     * Data-driven month-picker range (Task 4 of quick-260717-2q3).
     *
     * Replaces the hardcoded 24-month window with the actual data span:
     * min/max year+month across meal_entries, expenses, payments, and
     * monthly_closings for this mess. Fallback when no data exists: the
     * last 12 months ending at the current month (still NOT the old 24).
     *
     * @return array{first: array{year:int,month:int}, last: array{year:int,month:int}}
     */
    public function availableMonthRange(int $messId): array
    {
        $earliest = null;
        $latest = null;

        // Four small queries (kept separate for readability — NO UNION).
        // Each returns either a YYYY-MM-01 date or null; we merge into the
        // running min/max. Use query builder (no BelongsToActiveMess on the
        // raw builder) and scope by mess_id explicitly.
        $sources = [
            DB::table('meal_entries')->where('mess_id', $messId)->min('date'),
            DB::table('expenses')->where('mess_id', $messId)->min('date'),
            DB::table('payments')->where('mess_id', $messId)->min('date'),
            $this->closingMinYearMonth($messId),
        ];
        foreach ($sources as $value) {
            if (! filled($value)) {
                continue;
            }
            try {
                $carbon = Carbon::parse($value)->startOfMonth();
                if ($earliest === null || $carbon < $earliest) {
                    $earliest = $carbon;
                }
            } catch (\Throwable) {
                // Skip malformed dates silently.
            }
        }

        $maxSources = [
            DB::table('meal_entries')->where('mess_id', $messId)->max('date'),
            DB::table('expenses')->where('mess_id', $messId)->max('date'),
            DB::table('payments')->where('mess_id', $messId)->max('date'),
            $this->closingMaxYearMonth($messId),
        ];
        foreach ($maxSources as $value) {
            if (! filled($value)) {
                continue;
            }
            try {
                $carbon = Carbon::parse($value)->startOfMonth();
                if ($latest === null || $carbon > $latest) {
                    $latest = $carbon;
                }
            } catch (\Throwable) {
                // Skip malformed dates silently.
            }
        }

        // Default fallback: last 12 months ending current month (NOT 24).
        $first = $earliest ?? now()->copy()->subMonths(11)->startOfMonth();
        // Upper bound is ALWAYS the current month — the user may navigate to
        // "this month" even when no data has been entered yet. Per the plan
        // example (single 2025-03 expense, current month 2026-07): the
        // dropdown spans Mar-2025..Jul-2026 inclusive.
        $last = now()->copy()->startOfMonth();

        // Clamp: never show months past the current month, and never have
        // first > last.
        if ($last->greaterThan(now()->startOfMonth())) {
            $last = now()->startOfMonth();
        }
        if ($first->greaterThan($last)) {
            $first = $last->copy()->subMonths(11);
        }

        return [
            'first' => ['year' => $first->year, 'month' => $first->month],
            'last' => ['year' => $last->year, 'month' => $last->month],
        ];
    }

    /**
     * Build a YYYY-MM-01 string for the earliest monthly_closing row.
     * monthly_closings stores year+month as separate int columns.
     */
    private function closingMinYearMonth(int $messId): ?string
    {
        $row = DB::table('monthly_closings')
            ->where('mess_id', $messId)
            ->orderBy('year')
            ->orderBy('month')
            ->first(['year', 'month']);

        if (! $row) {
            return null;
        }

        return sprintf('%04d-%02d-01', (int) $row->year, (int) $row->month);
    }

    private function closingMaxYearMonth(int $messId): ?string
    {
        $row = DB::table('monthly_closings')
            ->where('mess_id', $messId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first(['year', 'month']);

        if (! $row) {
            return null;
        }

        return sprintf('%04d-%02d-01', (int) $row->year, (int) $row->month);
    }

    /**
     * @return array{year:int,month:int,total_bazar:float,total_meals:float,meal_rate:float,total_fixed:float,days_in_month:int,members:array<int,array<string,mixed>>,source:string}
     */
    public function monthlyReport(int $year, int $month): array
    {
        $messId = Mess::activeId();

        $closing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($closing) {
            return $this->monthlyFromSnapshot($closing, $year, $month);
        }

        $live = $this->preview->preview($year, $month);
        $live['source'] = 'live';

        return $live;
    }

    /**
     * Assemble the same shape BillPreviewService::preview() returns, but
     * read from the immutable MonthlyMemberSummary snapshot rows.
     *
     * @return array{year:int,month:int,total_bazar:float,total_meals:float,meal_rate:float,total_fixed:float,days_in_month:int,members:array<int,array<string,mixed>>,source:string}
     */
    private function monthlyFromSnapshot(MonthlyClosing $closing, int $year, int $month): array
    {
        $rows = MonthlyMemberSummary::query()
            ->where('monthly_closing_id', $closing->id)
            ->with('member:id,name,status')
            ->orderBy('member_id')
            ->get();

        $activeStatusVal = MemberStatus::ACTIVE instanceof \BackedEnum ? MemberStatus::ACTIVE->value : (string) MemberStatus::ACTIVE;

        $members = [];
        foreach ($rows as $row) {
            $closingBalance = $row->closing_balance !== null ? (float) $row->closing_balance : null;
            if ($closingBalance === null) {
                $advanceBalance = 0.0;
                $runningDue = (float) $row->balance_due;
            } else {
                $advanceBalance = $closingBalance >= 0 ? $closingBalance : 0.0;
                $runningDue = $closingBalance < 0 ? abs($closingBalance) : 0.0;
            }

            $meals = (float) $row->total_meals;
            $bill = (float) $row->gross_bill;
            $billPayments = (float) $row->advance_applied;
            $broughtForward = $row->brought_forward !== null ? (float) $row->brought_forward : 0.0;
            $status = $row->member?->status ?? 'active';
            $statusStr = $status instanceof \BackedEnum ? $status->value : (string) $status;
            $isActive = strcasecmp($statusStr, $activeStatusVal) === 0;

            // Safeguard: Omit former members in historical snapshots if they carried zero activity/balance
            if (! $isActive && $meals < 0.001 && $bill < 0.001 && $billPayments < 0.001 && abs($broughtForward) < 0.01 && $runningDue < 0.01 && $advanceBalance < 0.01) {
                continue;
            }

            $members[] = [
                'member_id' => $row->member_id,
                'name' => $row->member?->name ?? (string) $row->member_id,
                'meals' => $meals,
                'meal_cost' => (float) $row->meal_cost,
                'fixed_share' => (float) $row->fixed_cost_share,
                'guest_total' => (float) $row->guest_meal_charge,
                'bill' => $bill,
                'bill_payments' => $billPayments,
                'advance_payments' => 0.0,
                'advance_applied' => $billPayments,
                'due' => (float) $row->balance_due,
                'brought_forward' => $broughtForward,
                'advance_balance' => $advanceBalance,
                'due_balance' => $runningDue,
                'active_days' => 0,
                'status' => $status,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => (float) $closing->total_bazar,
            'total_meals' => (float) $closing->total_meals,
            'meal_rate' => (float) $closing->meal_rate,
            'total_fixed' => (float) $closing->total_fixed_expense,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'members' => $members,
            'source' => 'snapshot',
        ];
    }

    public function expenseReport(array $filters): array
    {
        $query = Expense::query()
            ->with(['category:id,name,kind', 'purchasedByMember:id,name', 'enteredBy:id,name'])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($filters['month'] ?? null, function ($q, $m) {
                [$y, $mo] = array_map('intval', explode('-', (string) $m));
                $q->whereYear('date', $y)->whereMonth('date', $mo);
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $rows = $query->paginate(50)->appends(request()->query());
        $totals = ['amount' => (float) (clone $query)->sum('amount')];

        return compact('rows', 'totals');
    }

    public function paymentReport(array $filters): array
    {
        $query = Payment::query()
            ->with(['member:id,name', 'enteredBy:id,name'])
            ->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', $id))
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('date', '<=', $d))
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $rows = $query->paginate(50)->appends(request()->query());
        $totals = ['amount' => (float) (clone $query)->sum('amount')];

        return compact('rows', 'totals');
    }
}