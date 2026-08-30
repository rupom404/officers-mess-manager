<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Jobs\CloseMonthJob;
use App\Models\Expense;
use App\Models\MealEntry;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Services\MonthCloseService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MonthCloseController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId();

        // If already closed, show the closed summary view
        $existing = MonthlyClosing::query()
            ->where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing) {
            return view('mess.closings.show', [
                'closing' => $existing,
                'summaries' => $existing->monthlyMemberSummaries ?? $existing->memberSummaries ?? collect(),
            ]);
        }

        // Live calculation preview
        $totalBazar = (float) Expense::query()
            ->where('mess_id', $messId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $mealModel = class_exists(MealEntry::class) ? MealEntry::class : \App\Models\Meal::class;
        $totalMeals = (float) $mealModel::query()
            ->whereHas('member', fn ($q) => $q->where('mess_id', $messId))
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('count');

        $mealRate = $totalMeals > 0 ? ($totalBazar / $totalMeals) : 0.0;

        return view('mess.close.index', [
            'year' => $year,
            'month' => $month,
            'periodLabel' => Carbon::create($year, $month, 1)->format('F Y'),
            'totalBazar' => $totalBazar,
            'totalMeals' => $totalMeals,
            'mealRate' => $mealRate,
            'totalFixed' => 0.0,
        ]);
    }

    public function store(Request $request, MonthCloseService $service): RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId();

        try {
            if (method_exists($service, 'close')) {
                $service->close($messId, $year, $month, auth()->id());
            } elseif (method_exists($service, 'execute')) {
                $service->execute($messId, $year, $month, auth()->id());
            } elseif (method_exists($service, 'closeMonth')) {
                $service->closeMonth($messId, $year, $month, auth()->id());
            } else {
                CloseMonthJob::dispatchSync($messId, $year, $month, auth()->id());
            }

            return redirect()->route('mess.closings.index')->with('success', __(':period has been successfully closed and locked.', [
                'period' => Carbon::create($year, $month, 1)->format('F Y'),
            ]));
        } catch (\Throwable $e) {
            Log::error('Month close error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to close month: ' . $e->getMessage());
        }
    }
}