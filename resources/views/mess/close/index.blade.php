@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            {{ __('Close month') }}
        </h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ __('Snapshot the mess for :period.', ['period' => $periodLabel ?? 'August 2026']) }}
        </p>
    </header>

    <!-- Stat Overview Summary -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL BAZAR') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($totalBazar ?? 16830), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL MEALS') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ number_format((float) ($totalMeals ?? 322), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('MEAL RATE') }}</span>
            <p class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">৳{{ number_format((float) ($mealRate ?? 52.27), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL FIXED') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($totalFixed ?? 0), 2) }}</p>
        </div>
    </div>

    <!-- Direct Close Month Action Form -->
    <div class="pt-2">
        <form method="POST" 
              action="{{ route('mess.close.store') }}" 
              onsubmit="return confirm('Are you sure you want to close this month? This will lock August 2026 and forward all member balances (dues and credits) into September.')">
            @csrf
            
            <input type="hidden" name="year" value="{{ $year ?? 2026 }}">
            <input type="hidden" name="month" value="{{ $month ?? 8 }}">

            <button type="submit" 
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-md transition-all duration-150 hover:bg-emerald-700 active:scale-95">
                {{ __('Close August 2026') }}
            </button>
        </form>
    </div>
</div>
@endsection