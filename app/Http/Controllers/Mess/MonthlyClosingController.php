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
        $messId = Mess::activeId();

        $closings = MonthlyClosing::query()
            ->when($messId, fn ($q) => $q->where('mess_id', $messId))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return view('mess.closings.index', [
            'closings' => $closings,
        ]);
    }

    public function show(MonthlyClosing $closing): View
    {
        $summaries = $closing->monthlyMemberSummaries 
            ?? $closing->memberSummaries 
            ?? $closing->summaries 
            ?? collect();

        return view('mess.closings.show', [
            'closing' => $closing,
            'summaries' => $summaries,
        ]);
    }
}