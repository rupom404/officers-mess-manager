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

    public function show(MonthlyClosing $closing): View
    {
        return view('mess.closings.show', [
            'closing' => $closing,
        ]);
    }
}