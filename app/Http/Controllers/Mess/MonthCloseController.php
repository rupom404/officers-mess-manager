<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Services\DashboardService;
use App\Services\MonthCloseService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MonthCloseController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId() ?? 1;

        // Check if already closed
        $existing = null;
        try {
            $existing = MonthlyClosing::query()
                ->where('mess_id', $messId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Could not check existing closing: ' . $e->getMessage());
        }

        if ($existing) {
            return view('mess.closings.show', [
                'closing' => $existing,
            ]);
        }

        // Pull verified stats from DashboardService
        $cards = [];
        try {
            $cards = $dashboardService->managerCards();
        } catch (\Throwable $e) {
            $cards = [];
        }

        $totalBazar = (float) ($cards['monthly_expenses'] ?? 0);
        $totalMeals = (float) ($cards['total_meals'] ?? 0);
        $mealRate = (float) ($cards['meal_rate'] ?? 0);

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
        $messId = Mess::activeId() ?? 1;

        try {
            // Execute closing synchronously in real-time
            if (method_exists($service, 'close')) {
                $service->close($messId, $year, $month, auth()->id());
            } elseif (method_exists($service, 'execute')) {
                $service->execute($messId, $year, $month, auth()->id());
            } elseif (method_exists($service, 'closeMonth')) {
                $service->closeMonth($messId, $year, $month, auth()->id());
            }

            return redirect()->route('mess.closings.index')->with('success', __(':period has been successfully closed and locked.', [
                'period' => Carbon::create($year, $month, 1)->format('F Y'),
            ]));
        } catch (\Throwable $e) {
            Log::error('Month close error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to close month: ' . $e->getMessage());
        }
    }
}