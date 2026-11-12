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
    public function edit(\App\Models\GuestMeal $guestMeal)
    {
        // Safe fetch: load all members for this mess and manually filter in PHP 
        // to avoid any Postgres column type mismatch on 'is_active' or 'status' Enums.
        $members = \App\Models\Member::where('mess_id', \App\Models\Mess::activeId())
            ->get()
            ->filter(function ($member) {
                $status = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                return strcasecmp($status, 'active') === 0;
            })
            ->values();
            
        $mealTypes = \App\Support\MealType::cases();

        // Pass an empty breadcrumb array to bypass the Tyro Service Provider crash
        return view('mess.guest-meals.edit', [
            'guestMeal' => $guestMeal,
            'members' => $members,
            'mealTypes' => $mealTypes,
            'breadcrumbs' => []
        ]);
    }

    public function update(UpdateGuestMealRequest $request, GuestMeal $guestMeal): RedirectResponse
    {
        $this->service->update($guestMeal, $request->validated());

        return redirect()
            ->route('mess.guest-meals.index')
            ->with('success', __('Guest meal updated.'));
    }
    /**
     * Remove the specified guest meal from storage.
     */
    public function destroy(\App\Models\GuestMeal $guestMeal)
    {
        $guestMeal->delete();

        return redirect()->route('mess.guest-meals.index')
            ->with('success', __('Guest meal permanently deleted.'));
    }
}
