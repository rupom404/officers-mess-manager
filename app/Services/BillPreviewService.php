<?php

namespace App\Services;

use App\Models\AdvanceBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use App\Models\MessClosedDay;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Support\ExpenseKind;
use App\Support\MealType;
use App\Support\MemberStatus;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BillPreviewService
{
    public function preview(int $year, int $month): array
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return $this->emptyPreview($year, $month);
        }

        $cacheKey = $this->cacheKey($messId, $year, $month);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($messId, $year, $month) {
            return $this->compute($messId, $year, $month);
        });
    }

    public function forMember(int $memberId, int $year, int $month): ?array
    {
        $preview = $this->preview($year, $month);

        foreach ($preview['members'] as $row) {
            if ((int) $row['member_id'] === $memberId) {
                return $row;
            }
        }

        return null;
    }

    public function cacheKey(int $messId, int $year, int $month): string
    {
        return "bill-preview:v5:{$messId}:{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    public function invalidate(int $year, int $month): void
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return;
        }

        Cache::forget($this->cacheKey($messId, $year, $month));
    }

    private function emptyPreview(int $year, int $month): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => 0.0,
            'total_meals' => 0.0,
            'meal_rate' => 0.0,
            'total_fixed' => 0.0,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'members' => [],
            'brought_forward' => 0.0,
        ];
    }

    private function compute(int $messId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $daysInMonth = $start->daysInMonth;

        $bazarCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::BAZAR)
            ->pluck('id')
            ->all();

        $fixedCategoryIds = ExpenseCategory::query()
            ->where('kind', ExpenseKind::FIXED)
            ->pluck('id')
            ->all();

        $totalBazar = (float) Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('expense_category_id', $bazarCategoryIds)
            ->sum('amount');

        $totalFixed = (float) Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('expense_category_id', $fixedCategoryIds)
            ->sum('amount');

        $members = Member::query()
            ->where('mess_id', $messId)
            ->whereIn('status', [MemberStatus::ACTIVE, MemberStatus::FORMER])
            ->orderBy('name')
            ->get();

        $memberIds = $members->pluck('id')->all();

        $closedDates = MessClosedDay::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->values()
            ->all();

        $disabledDayRows = MemberDisabledDay::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'date']);

        $disabledDaysByMember = [];
        foreach ($disabledDayRows as $dd) {
            $ds = $dd->date instanceof Carbon ? $dd->date->toDateString() : (string) $dd->date;
            $disabledDaysByMember[$dd->member_id][] = $ds;
        }

        $closedDatesSet = array_flip($closedDates);

        $mealTotalsByMember = $this->mealTotals($memberIds, $start, $end, $closedDatesSet, $disabledDaysByMember);
        $guestTotalsByMember = $this->guestTotals($memberIds, $start, $end);
        $paymentsByMember = $this->paymentsByMember($memberIds, $start, $end);
        
        // Fetch explicit brought-forward balances from the prior month's snapshot
        $broughtForwardBalances = $this->exactBroughtForwardBalances($messId, $memberIds, $year, $month);
        $advanceBalances = $this->advanceBalances($memberIds);

        $totalMeals = 0.0;
        foreach ($members as $member) {
            $totalMeals += ($mealTotalsByMember[$member->id] ?? 0.0) + ($guestTotalsByMember[$member->id] ?? 0.0);
        }

        $mealRate = $totalMeals > 0 ? round($totalBazar / $totalMeals, 2) : 0.0;

        $disabledDayCountByMember = [];
        foreach ($memberIds as $mid) {
            $disabledDayCountByMember[$mid] = count($disabledDaysByMember[$mid] ?? []);
        }

        $activeStatusVal = MemberStatus::ACTIVE instanceof \BackedEnum ? MemberStatus::ACTIVE->value : (string) MemberStatus::ACTIVE;

        $rows = [];
        foreach ($members as $member) {
            $regMeals = $mealTotalsByMember[$member->id] ?? 0.0;
            $gstMeals = $guestTotalsByMember[$member->id] ?? 0.0;
            $meals = $regMeals + $gstMeals;

            $mealCost = round($meals * $mealRate, 2);

            $activeDays = $this->activeDaysForMember($member, $start, $end, $closedDatesSet, $disabledDayCountByMember[$member->id] ?? 0);
            $fixedShare = $daysInMonth > 0
                ? round($totalFixed * ($activeDays / $daysInMonth), 2)
                : 0.0;

            $guestTotal = 0.0;
            $bill = round($mealCost + $fixedShare + $guestTotal, 2);

            $billPayments = $paymentsByMember[$member->id]['bill_payments'] ?? 0.0;
            $advanceBalance = $advanceBalances[$member->id]['balance'] ?? 0.0;
            $dueBalance = $advanceBalances[$member->id]['due_balance'] ?? 0.0;
            $advancePayments = $paymentsByMember[$member->id]['advance_payments'] ?? 0.0;

            // Use the explicit snapshot balance from last month, falling back to member.opening_balance
            $broughtForward = $broughtForwardBalances[$member->id] ?? round(($advanceBalance - $dueBalance) - $advancePayments, 2);
            
            $owedAfterPayments = max(0.0, $bill - $billPayments);
            $advanceApplied = min($advanceBalance, $owedAfterPayments);
            $due = round($owedAfterPayments - $advanceApplied, 2);

            $memberStatusStr = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
            $isActive = strcasecmp($memberStatusStr, $activeStatusVal) === 0;

            $hasMeals = $meals > 0.001;
            $hasPayments = ($billPayments > 0.001) || ($advancePayments > 0.001);
            $hasBill = $bill > 0.001;
            $hasBalance = (abs($broughtForward) > 0.01) || ($due > 0.01) || ($advanceApplied > 0.01) || (abs($advanceBalance - $dueBalance) > 0.01);

            $wasActiveInMonth = false;
            if ($member->leaving_date) {
                $leavingDate = $member->leaving_date instanceof Carbon ? $member->leaving_date : Carbon::parse($member->leaving_date);
                $joiningDate = $member->joining_date
                    ? ($member->joining_date instanceof Carbon ? $member->joining_date : Carbon::parse($member->joining_date))
                    : null;

                $wasActiveInMonth = $leavingDate->startOfDay()->gte($start)
                    && (! $joiningDate || $joiningDate->startOfDay()->lte($end));
            }

            if (! $isActive && ! $hasMeals && ! $hasPayments && ! $hasBill && ! $hasBalance && ! $wasActiveInMonth) {
                continue;
            }

            $rows[] = [
                'member_id' => $member->id,
                'name' => $member->name,
                'meals' => $meals,
                'meal_cost' => $mealCost,
                'fixed_share' => $fixedShare,
                'guest_total' => $guestTotal,
                'bill' => $bill,
                'bill_payments' => $billPayments,
                'advance_payments' => $advancePayments,
                'advance_applied' => $advanceApplied,
                'due' => $due,
                'brought_forward' => $broughtForward,
                'advance_balance' => $advanceBalance,
                'due_balance' => $dueBalance,
                'active_days' => $activeDays,
                'status' => $member->status,
                'disabled_days' => $disabledDayCountByMember[$member->id] ?? 0,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_bazar' => $totalBazar,
            'total_meals' => $totalMeals,
            'meal_rate' => $mealRate,
            'total_fixed' => $totalFixed,
            'days_in_month' => $daysInMonth,
            'closed_days_count' => count($closedDates),
            'members' => $rows,
        ];
    }

    /**
     * Look back exactly one month and extract the locked closing balances to use as this month's Brought Forward.
     */
    private function exactBroughtForwardBalances(int $messId, array $memberIds, int $year, int $month): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $targetDate = Carbon::create($year, $month, 1)->subMonth();
        $targetYear = $targetDate->year;
        $targetMonth = $targetDate->month;

        $closing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $targetYear)
            ->where('month', $targetMonth)
            ->first();

        $balances = [];

        if ($closing) {
            $summaries = MonthlyMemberSummary::query()
                ->where('monthly_closing_id', $closing->id)
                ->whereIn('member_id', $memberIds)
                ->get(['member_id', 'closing_balance']);

            foreach ($summaries as $summary) {
                if ($summary->closing_balance !== null) {
                    $balances[$summary->member_id] = (float) $summary->closing_balance;
                }
            }
        }

        // Fill missing balances from the members table (for brand new members who have no history)
        if (count($balances) < count($memberIds) && Schema::hasColumn('members', 'opening_balance')) {
            $members = Member::withoutGlobalScopes()
                ->whereIn('id', $memberIds)
                ->get(['id', 'opening_balance']);
                
            foreach ($members as $m) {
                if (!isset($balances[$m->id])) {
                    $balances[$m->id] = (float) ($m->opening_balance ?? 0.0);
                }
            }
        }

        return $balances;
    }

    private function mealTotals(array $memberIds, Carbon $start, Carbon $end, array $closedDatesSet = [], array $disabledDaysByMember = []): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $entries = MealEntry::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'date', 'breakfast', 'lunch', 'dinner']);

        $totals = array_fill_keys($memberIds, 0.0);
        foreach ($entries as $entry) {
            $dateStr = $entry->date->toDateString();
            if (isset($closedDatesSet[$dateStr])) {
                continue;
            }
            $memberDisabled = $disabledDaysByMember[$entry->member_id] ?? [];
            if (in_array($dateStr, $memberDisabled, true)) {
                continue;
            }

            $val = 0.0;
            if ($entry->breakfast) {
                $val += MealType::value(MealType::BREAKFAST);
            }
            if ($entry->lunch) {
                $val += MealType::value(MealType::LUNCH);
            }
            if ($entry->dinner) {
                $val += MealType::value(MealType::DINNER);
            }
            $totals[$entry->member_id] = ($totals[$entry->member_id] ?? 0.0) + $val;
        }

        return $totals;
    }

    private function guestTotals(array $memberIds, Carbon $start, Carbon $end): array
    {
        if (empty($memberIds) || !class_exists(GuestMeal::class) || !Schema::hasTable('guest_meals')) {
            return [];
        }

        $cols = Schema::getColumnListing('guest_meals');
        $col = in_array('count', $cols) ? 'count' : (in_array('meals', $cols) ? 'meals' : 'charge_amount');

        $rows = GuestMeal::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', $col]);

        $totals = array_fill_keys($memberIds, 0.0);
        foreach ($rows as $row) {
            $totals[$row->member_id] = ($totals[$row->member_id] ?? 0.0) + (float) $row->{$col};
        }

        return $totals;
    }

    private function paymentsByMember(array $memberIds, Carbon $start, Carbon $end): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $rows = Payment::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['member_id', 'type', 'amount']);

        $out = [];
        foreach ($memberIds as $id) {
            $out[$id] = ['bill_payments' => 0.0, 'advance_payments' => 0.0];
        }
        foreach ($rows as $row) {
            $id = $row->member_id;
            if ($row->type === PaymentType::ADVANCE_DEPOSIT) {
                $out[$id]['advance_payments'] += (float) $row->amount;
            } else {
                $out[$id]['bill_payments'] += (float) $row->amount;
            }
        }

        return $out;
    }

    private function advanceBalances(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $rows = AdvanceBalance::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'balance', 'due_balance']);

        $out = [];
        foreach ($memberIds as $id) {
            $out[$id] = ['balance' => 0.0, 'due_balance' => 0.0];
        }
        foreach ($rows as $row) {
            $out[$row->member_id] = [
                'balance' => (float) $row->balance,
                'due_balance' => (float) $row->due_balance,
            ];
        }

        return $out;
    }

    private function activeDaysForMember(Member $member, Carbon $start, Carbon $end, array $closedDatesSet = [], int $disabledDayCount = 0): int
    {
        $statusStr = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
        $activeStatusVal = MemberStatus::ACTIVE instanceof \BackedEnum ? MemberStatus::ACTIVE->value : (string) MemberStatus::ACTIVE;
        $isActive = strcasecmp($statusStr, $activeStatusVal) === 0;

        if (! $isActive && ! $member->leaving_date) {
            return 0;
        }

        $memberStart = $member->joining_date && $member->joining_date->gt($start)
            ? $member->joining_date->copy()
            : $start->copy();

        $memberEnd = $member->leaving_date && $member->leaving_date->lt($end)
            ? $member->leaving_date->copy()
            : $end->copy();

        if ($memberEnd->lt($memberStart)) {
            return 0;
        }

        $activeDays = (int) $memberStart->diffInDays($memberEnd) + 1;

        if (! empty($closedDatesSet)) {
            $cursor = $memberStart->copy();
            while ($cursor <= $memberEnd) {
                if (isset($closedDatesSet[$cursor->toDateString()])) {
                    $activeDays--;
                }
                $cursor->addDay();
            }
        }

        return max(0, $activeDays - $disabledDayCount);
    }
}