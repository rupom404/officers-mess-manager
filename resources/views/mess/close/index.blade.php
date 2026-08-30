@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Monthly Closings') }}
            </h1>
            <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ __('Historical locked month snapshots and audit records.') }}
            </p>
        </div>
        <a href="{{ route('mess.close.index') }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-700 active:scale-95">
            {{ __('Close Active Month') }}
        </a>
    </header>

    @if ($closings->isEmpty())
        <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-slate-900 dark:text-white">{{ __('No Closed Months Yet') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('When you close a month, its historical financial snapshot will appear here.') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($closings as $closing)
                @php
                    $period = \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y');
                @endphp
                <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-[#111827]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-black text-slate-900 dark:text-white">{{ $period }}</span>
                        <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            {{ __('Locked') }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 border-y border-slate-100 py-3 text-xs dark:border-slate-800">
                        <div>
                            <span class="text-slate-400 dark:text-slate-500">{{ __('Total Bazar') }}</span>
                            <p class="mt-0.5 font-bold text-slate-800 dark:text-slate-200">৳{{ number_format((float) $closing->total_bazar, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500">{{ __('Total Meals') }}</span>
                            <p class="mt-0.5 font-bold text-slate-800 dark:text-slate-200">{{ number_format((float) $closing->total_meals, 1) }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500">{{ __('Meal Rate') }}</span>
                            <p class="mt-0.5 font-bold text-emerald-600 dark:text-emerald-400">৳{{ number_format((float) $closing->meal_rate, 2) }}</p>
                        </div>
                        <div>
                            <span class="text-slate-400 dark:text-slate-500">{{ __('Fixed Costs') }}</span>
                            <p class="mt-0.5 font-bold text-slate-800 dark:text-slate-200">৳{{ number_format((float) ($closing->total_fixed ?? 0), 2) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end">
                        <a href="{{ route('mess.closings.show', $closing) }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                            {{ __('View Snapshot') }} &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $closings->links() }}
        </div>
    @endif
</div>
@endsection