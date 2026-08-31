<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Support\MemberStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonthlyClosingController extends Controller
{
    public function index(Request $request): View
    {
        $messId = Mess::activeId() ?? 1;

        try {
            $closings = MonthlyClosing::query()
                ->where('mess_id', $messId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->paginate(12);
        } catch (\Throwable $e) {
            $closings = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12);
        }

        return view('mess.closings.index', [
            'closings' => $closings,
        ]);
    }

    public function show($closing): View
    {
        if (! ($closing instanceof MonthlyClosing)) {
            $closing = MonthlyClosing::findOrFail($closing);
        }

        $summaries = collect();
        try {
            if (method_exists($closing, 'monthlyMemberSummaries')) {
                $summaries = $closing->monthlyMemberSummaries()->with('member')->get();
            } elseif (method_exists($closing, 'memberSummaries')) {
                $summaries = $closing->memberSummaries()->with('member')->get();
            }
        } catch (\Throwable $e) {
            $summaries = collect();
        }

        $activeMembers = Member::query()
            ->where('mess_id', Mess::activeId() ?? 1)
            ->where('status', MemberStatus::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('mess.closings.show', [
            'closing' => $closing,
            'summaries' => $summaries,
            'activeMembers' => $activeMembers,
            'nextYear' => Carbon::create($closing->year, $closing->month, 1)->addMonth()->year,
            'nextMonth' => Carbon::create($closing->year, $closing->month, 1)->addMonth()->month,
        ]);
    }

    /**
     * Start a fresh operating month without deleting historical data.
     * The previous month remains locked; current running credit/debt is reset
     * to zero because the manager has confirmed it was settled manually.
     */
    public function startNextMonth(Request $request, $closing)
    {
        $closing = $closing instanceof MonthlyClosing
            ? $closing
            : MonthlyClosing::findOrFail($closing);

        $request->validate([
            'retiring_member_ids' => ['array'],
            'retiring_member_ids.*' => ['integer'],
        ]);

        $messId = Mess::activeId() ?? 1;
        $next = Carbon::create($closing->year, $closing->month, 1)->addMonth();
        $retiringIds = collect($request->input('retiring_member_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($messId, $next, $retiringIds) {
            // One-time September reset: preserve August history, but begin the
            // new operating cycle with zero opening credit/debt.
            AdvanceBalance::query()
                ->where('mess_id', $messId)
                ->whereHas('member', fn ($q) => $q->where('status', MemberStatus::ACTIVE))
                ->update([
                    'balance' => '0.00',
                    'due_balance' => '0.00',
                    'last_updated_at' => now(),
                ]);

            if ($retiringIds->isNotEmpty()) {
                Member::query()
                    ->where('mess_id', $messId)
                    ->whereIn('id', $retiringIds->all())
                    ->where('status', MemberStatus::ACTIVE)
                    ->update([
                        'status' => MemberStatus::FORMER,
                        'leaving_date' => $next->copy()->subDay()->toDateString(),
                    ]);
            }
        });

        return redirect()
            ->route('mess.reports.monthly', ['year' => $next->year, 'month' => $next->month])
            ->with('success', sprintf(
                '%s started as a fresh month. Existing balances were reset to ৳0.00 and %d member(s) were marked former.',
                $next->format('F Y'),
                $retiringIds->count()
            ));
    }
}
