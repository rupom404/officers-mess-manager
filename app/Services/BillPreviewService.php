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
        return "bill-preview:v2:{$messId}:{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT);
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
        $advanceBalances = $this->advanceBalances($memberIds);

        // Incorporate guest meals natively into the mess total meals
        $totalMeals = 0.0;
        foreach ($members as $member) {
            $totalMeals += ($mealTotalsByMember[$member->id] ?? 0.0) + ($guestTotalsByMember[$member->id] ?? 0.0);
        }

        $mealRate = $totalMeals > 0 ? round($totalBazar / $totalMeals, 2) : 0.0;

        $disabledDayCountByMember = [];
        foreach ($memberIds as $mid) {
            $disabledDayCountByMember[$mid] = count($disabledDaysByMember[$mid] ?? []);
        }

        $rows = [];
        foreach ($members as $member) {
            $regMeals = $mealTotalsByMember[$member->id] ?? 0.0;
            $gstMeals = $guestTotalsByMember[$member->id] ?? 0.0;
            $meals = $regMeals + $gstMeals; // Host + Guest total combined
            
            $mealCost = round($meals * $mealRate, 2);

            $activeDays = $this->activeDaysForMember($member, $start, $end, $closedDatesSet, $disabledDayCountByMember[$member->id] ?? 0);
            $fixedShare = $daysInMonth > 0
                ? round($totalFixed * ($activeDays / $daysInMonth), 2)
                : 0.0;

            $guestTotal = 0.0; // Nullify isolated flat cash charge to prevent double billing
            $bill = round($mealCost + $fixedShare + $guestTotal, 2);

            $billPayments = $paymentsByMember[$member->id]['bill_payments'] ?? 0.0;
            $advanceBalance = $advanceBalances[$member->id]['balance'] ?? 0.0;
            $dueBalance = $advanceBalances[$member->id]['due_balance'] ?? 0.0;
            $advancePayments = $paymentsByMember[$member->id]['advance_payments'] ?? 0.0;

            $broughtForward = round(($advanceBalance - $dueBalance) - $advancePayments, 2);
            $owedAfterPayments = max(0.0, $bill - $billPayments);
            $advanceApplied = min($advanceBalance, $owedAfterPayments);
            $due = round($owedAfterPayments - $advanceApplied, 2);

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