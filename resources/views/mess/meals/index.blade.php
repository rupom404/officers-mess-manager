@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold leading-tight tracking-tight text-slate-900">{{ __('Daily meal grid') }}</h1>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-600">{{ __('Mark which meals each member took on this date. Save once at the bottom.') }}</p>
        </div>
        <x-mess-date-nav :date="$date" />
    </header>

    <form method="POST" action="{{ route('mess.meals.save') }}" data-meal-grid-form>
        @csrf
        <input type="hidden" name="date" value="{{ $date }}" />

        @if ($isClosed ?? false)
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ __('This day is marked as a mess closed day. Meals cannot be edited.') }}
            </div>
        @endif

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ __('Quick actions') }}</p>
                <p class="text-xs text-slate-500">{{ __('Set the same meal pattern for every editable member.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" data-preset="all" class="btn btn-secondary touch-target">{{ __('Mark all 3 meals') }}</button>
                <button type="button" data-preset="none" class="btn btn-secondary touch-target">{{ __('Mark all 0 meals') }}</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 min-w-[13rem] bg-slate-50 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-4">{{ __('Member') }}</th>
                        <th scope="col" class="min-w-[6.5rem] px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-4">{{ __('Breakfast') }}</th>
                        <th scope="col" class="min-w-[6.5rem] px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-4">{{ __('Lunch') }}</th>
                        <th scope="col" class="min-w-[6.5rem] px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-4">{{ __('Dinner') }}</th>
                        <th scope="col" class="min-w-[10rem] px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500 sm:px-4"><span class="sr-only">{{ __('Member quick actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($rows as $row)
                        <tr @class(['opacity-60' => !$row->editable]) aria-disabled="{{ $row->editable ? 'false' : 'true' }}">
                            <td class="sticky left-0 z-[1] bg-white px-3 py-3 text-sm sm:px-4">
                                <div class="max-w-[44vw] truncate font-semibold text-slate-900 sm:max-w-none">{{ $row->member->name }}</div>
                                @if ($row->member->room_or_seat)
                                    <div class="truncate text-xs text-slate-500">{{ $row->member->room_or_seat }}</div>
                                @endif
                                @if (!$row->editable)
                                    @if ($row->meal_off_until ?? false)
                                        <p class="mt-1 text-xs font-medium text-amber-700">{{ __('On meal off until :date', ['date' => $row->meal_off_until->format('d M')]) }}</p>
                                    @else
                                        <p class="mt-1 text-xs text-slate-400">{{ __('Day disabled') }}</p>
                                    @endif
                                @endif
                            </td>
                            @foreach (['breakfast', 'lunch', 'dinner'] as $meal)
                                <td class="px-2 py-2 text-center sm:px-4 sm:py-3">
                                    <input type="hidden" name="entries[{{ $row->member->id }}][member_id]" value="{{ $row->member->id }}" />
                                    <label class="inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-lg transition hover:bg-slate-50">
                                        <input type="checkbox"
                                            name="entries[{{ $row->member->id }}][{{ $meal }}]"
                                            value="1"
                                            @checked($row->{$meal})
                                            @disabled(!$row->editable)
                                            data-meal-checkbox
                                            data-member="{{ $row->member->id }}"
                                            data-meal="{{ $meal }}"
                                            class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring focus:ring-emerald-600 focus:ring-offset-1"
                                        />
                                    </label>
                                </td>
                            @endforeach
                            <td class="px-2 py-2 text-center sm:px-4 sm:py-3">
                                @if ($row->editable)
                                    <div class="inline-flex flex-wrap justify-center gap-1" role="group" aria-label="{{ __('Quick actions for :name', ['name' => $row->member->name]) }}">
                                        <button type="button" data-row-preset="all" data-row-member="{{ $row->member->id }}" class="btn btn-secondary btn-sm" aria-label="{{ __('All on for :name', ['name' => $row->member->name]) }}">B+L+D</button>
                                        <button type="button" data-row-preset="breakfast" data-row-member="{{ $row->member->id }}" class="btn btn-secondary btn-sm" aria-label="{{ __('Breakfast only for :name', ['name' => $row->member->name]) }}">B</button>
                                        <button type="button" data-row-preset="lunch" data-row-member="{{ $row->member->id }}" class="btn btn-secondary btn-sm" aria-label="{{ __('Lunch only for :name', ['name' => $row->member->name]) }}">L</button>
                                        <button type="button" data-row-preset="dinner" data-row-member="{{ $row->member->id }}" class="btn btn-secondary btn-sm" aria-label="{{ __('Dinner only for :name', ['name' => $row->member->name]) }}">D</button>
                                        <button type="button" data-row-preset="none" data-row-member="{{ $row->member->id }}" class="btn btn-secondary btn-sm" aria-label="{{ __('All off for :name', ['name' => $row->member->name]) }}">×</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-600">{{ __('No active members yet. Add members to start recording meals.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save all changes') }}</button>
            <a href="{{ route('mess.meals.monthly', ['month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}" class="btn btn-ghost btn-sm">{{ __('Switch to monthly grid') }}</a>
        </div>
    </form>

    @once
        <script>
            (function () {
                document.querySelectorAll('[data-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const preset = btn.getAttribute('data-preset');
                        const value = preset === 'all';
                        document.querySelectorAll('[data-meal-checkbox]').forEach(function (cb) {
                            if (!cb.disabled) cb.checked = value;
                        });
                    });
                });
                document.querySelectorAll('[data-row-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const preset = btn.getAttribute('data-row-preset');
                        const memberId = btn.getAttribute('data-row-member');
                        const targetMeals = preset === 'all' ? ['breakfast', 'lunch', 'dinner'] : (preset === 'none' ? [] : [preset]);
                        document.querySelectorAll('[data-meal-checkbox][data-member="' + memberId + '"]').forEach(function (cb) {
                            if (!cb.disabled) cb.checked = targetMeals.indexOf(cb.getAttribute('data-meal')) !== -1;
                        });
                    });
                });
            })();
        </script>
    @endonce
@endsection
