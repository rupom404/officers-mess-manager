<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use Illuminate\Http\Request;
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
            } elseif (method_exists($closing, 'summaries')) {
                $summaries = $closing->summaries()->with('member')->get();
            }
        } catch (\Throwable $e) {
            $summaries = collect();
        }

        return view('mess.closings.show', [
            'closing'   => $closing,
            'summaries' => $summaries,
        ]);
    }
}