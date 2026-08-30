<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Jobs\CloseMonthJob;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthCloseController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId();

        // Check if already closed
        $existing = MonthlyClosing::where('mess_id', $messId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing) {
            return view('mess.closings.show', ['closing' => $existing]);
        }

        return view('mess.close.index', [
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $messId = Mess::activeId();

        // Run the closing job immediately in real-time
        CloseMonthJob::dispatchSync($messId, $year, $month, auth()->id());

        return redirect()->route('home')->with('success', __(':period has been successfully closed and locked.', [
            'period' => \Carbon\Carbon::create($year, $month, 1)->format('F Y'),
        ]));
    }
}