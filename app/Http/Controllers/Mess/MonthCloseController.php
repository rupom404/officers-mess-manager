<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
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
    public function index(Request $request, MonthCloseService $monthCloseService): View|RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId() ?? 1;

        try {
            $existing = MonthlyClosing::query()
                ->where('mess_id', $messId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                return redirect()->route('mess.closings.show', $existing->id);
            }
        } catch (\Throwable $e) {
            Log::warning('MonthCloseController check existing: ' . $e->getMessage());
        }

        $preview = $monthCloseService->preview($messId, $year, $month);

        return view('mess.close.index', [
            'year'        => $year,
            'month'       => $month,
            'periodLabel' => Carbon::create($year, $month, 1)->format('F Y'),
            'totalBazar'  => (float) ($preview['total_bazar'] ?? 0),
            'totalMeals'  => (float) ($preview['total_meals'] ?? 0),
            'mealRate'    => (float) ($preview['meal_rate'] ?? 0),
            'totalFixed'  => (float) ($preview['total_fixed'] ?? 0),
        ]);
    }

    public function store(Request $request, MonthCloseService $monthCloseService): RedirectResponse
    {
        return $this->handleClose($request, $monthCloseService);
    }

    public function trigger(Request $request, MonthCloseService $monthCloseService): RedirectResponse
    {
        return $this->handleClose($request, $monthCloseService);
    }

    protected function handleClose(Request $request, MonthCloseService $monthCloseService): RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId() ?? 1;
        $userId = auth()->id() ?? 1;

        try {
            $closing = $monthCloseService->close($messId, $year, $month, $userId);

            return redirect()->route('mess.closings.show', $closing->id)->with('success', __(':period has been successfully closed and locked.', [
                'period' => Carbon::create($year, $month, 1)->format('F Y'),
            ]));
        } catch (\Throwable $e) {
            Log::error('Failed to close month: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('mess.close.index', ['year' => $year, 'month' => $month])
                ->with('error', 'Failed to close month: ' . $e->getMessage());
        }
    }
}