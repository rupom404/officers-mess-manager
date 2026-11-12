<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreGuestMealRequest;
use App\Http\Requests\Mess\UpdateGuestMealRequest;
use App\Models\GuestMeal;
use App\Services\GuestMealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestMealController extends Controller
{
    public function __construct(private readonly GuestMealService $service) {}

    public function index(Request $request): View
    {
        $guestMeals = $this->service->list($request);

        return view('mess.guest-meals.index', compact('guestMeals'));
    }

    public function create(): View
    {
        return view('mess.guest-meals.create', ['guestMeal' => new GuestMeal]);
    }

    public function store(StoreGuestMealRequest $request): RedirectResponse
    {
        $guestMeal = $this->service->create($request->validated());

        return redirect()
            ->route('mess.guest-meals.index')
            ->with('success', __('Guest meal recorded for :name.', ['name' => $guestMeal->guest_name]));
    }

    /**
     * Show the form for editing the specified guest meal.
     */
    public function edit(GuestMeal $guestMeal)
    {
        return view('mess.guest-meals.create', ['guestMeal' => new GuestMeal]);
    }

    public function update(UpdateGuestMealRequest $request, GuestMeal $guestMeal): RedirectResponse
    {
        $this->service->update($guestMeal, $request->validated());

        return redirect()
            ->route('mess.guest-meals.index')
            ->with('success', __('Guest meal updated.'));
    }

    public function destroy(\App\Models\GuestMeal $guestMeal, \App\Services\BillPreviewService $previewService)
    {
        $date = $guestMeal->date instanceof \Carbon\Carbon 
            ? $guestMeal->date 
            : \Carbon\Carbon::parse($guestMeal->date);

        $guestMeal->delete();

        // Flush cache so host's meal count and monthly report update immediately
        $previewService->invalidate($date->year, $date->month);
        \Illuminate\Support\Facades\Cache::flush();

        return redirect()->route('mess.guest-meals.index')
            ->with('success', __('Guest meal deleted and deducted from host\'s meal total.'));
    }
}
