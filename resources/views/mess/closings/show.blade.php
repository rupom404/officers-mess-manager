@extends('layouts.app')

@section('content')
@php
    $period = \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y');
    $summaryList = $summaries ?? $closing->monthlyMemberSummaries ?? collect();
@endphp

<div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ $period }} {{ __('Closing Snapshot') }}
                </h1>
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-black uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    {{ __('Locked') }}
                </span>
            </div>
            <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ __('Closed on :date', ['date' => $closing->created_at?->format('d M Y, h:i A') ?? 'N/A']) }}
            </p>
        </div>
        
        <div class="flex items-center gap-2">
            <a href="{{ route('mess.reports.monthly', ['year' => $closing->year, 'month' => $closing->month]) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 dark:border-slate-800 dark:bg-[#111827] dark:text-slate-300 dark:hover:bg-[#1a2942]">
                {{ __('Monthly Report') }}
            </a>
            <a href="{{ route('mess.closings.index') }}" class="inline-flex items-center rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                &larr; {{ __('All Closings') }}
            </a>
        </div>
    </header>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL BAZAR') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($closing->total_bazar ?? 0), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL MEALS') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ number_format((float) ($closing->total_meals ?? 0), 1) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('FINAL MEAL RATE') }}</span>
            <p class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">৳{{ number_format((float) ($closing->meal_rate ?? 0), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL FIXED') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($closing->total_fixed ?? 0), 2) }}</p>
        </div>
    </div>

    <!-- Member Summaries Table -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-[#111827]">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Member Closing Balances') }}</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-[#0e1726] dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Member') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('Meals') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Meal Cost') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Paid') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Brought Forward') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Closing Net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($summaryList as $sum)
                        @php
                            $memberName = $sum->member->name ?? $sum->member_name ?? 'Member';
                            $net = (float) ($sum->closing_balance ?? $sum->closing_net ?? $sum->net_balance ?? 0);
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-semibold text-slate-900 dark:text-white">{{ $memberName }}</td>
                            <td class="px-5 py-3 text-center text-slate-700 dark:text-slate-300">{{ number_format((float) ($sum->meals ?? 0), 1) }}</td>
                            <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-300">৳{{ number_format((float) ($sum->meal_cost ?? 0), 2) }}</td>
                            <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-300">৳{{ number_format((float) ($sum->bill_payments ?? $sum->paid ?? 0), 2) }}</td>
                            <td class="px-5 py-3 text-right text-slate-700 dark:text-slate-300">৳{{ number_format((float) ($sum->brought_forward ?? 0), 2) }}</td>
                            <td class="px-5 py-3 text-right font-black {{ $net < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $net < 0 ? 'Owes ৳' . number_format(abs($net), 2) : 'Credit ৳' . number_format($net, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">
                                {{ __('No member summaries recorded for this closing.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection