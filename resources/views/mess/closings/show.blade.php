@extends('layouts.app')

@section('content')
@php
    $period = \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y');
    $summaryList = $summaries ?? $closing->monthlyMemberSummaries ?? collect();
@endphp
<div class="space-y-5">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $period }} {{ __('Closing Snapshot') }}</h1>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('Locked') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Closed on :date', ['date' => ($closing->closed_at ?? $closing->created_at)?->format('d M Y, h:i A') ?? 'N/A']) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.reports.monthly', ['year' => $closing->year, 'month' => $closing->month]) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm dark:border-slate-800 dark:bg-[#111827] dark:text-slate-300">{{ __('Monthly Report') }}</a>
            <a href="{{ route('mess.closings.index') }}" class="inline-flex items-center rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white dark:bg-emerald-600">&larr; {{ __('All Closings') }}</a>
        </div>
    </header>
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="dashboard-card rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827]"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total bazar') }}</span><p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">৳{{ number_format((float) ($closing->total_bazar ?? 0), 2) }}</p></div>
        <div class="dashboard-card rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827]"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total meals') }}</span><p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format((float) ($closing->total_meals ?? 0), 1) }}</p></div>
        <div class="dashboard-card rounded-2xl border border-emerald-200/80 bg-emerald-50/60 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-500/10"><span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">{{ __('Final meal rate') }}</span><p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-300">৳{{ number_format((float) ($closing->meal_rate ?? 0), 2) }}</p></div>
        <div class="dashboard-card rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827]"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total fixed') }}</span><p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">৳{{ number_format((float) ($closing->total_fixed_expense ?? 0), 2) }}</p></div>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-[#111827]">
        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"><h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Member closing balances') }}</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Immutable figures recorded when this month was closed.') }}</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm"><thead class="bg-slate-50 dark:bg-[#0e1726]"><tr class="border-b border-slate-200 dark:border-slate-800">
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meals') }}</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meal cost') }}</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Paid') }}</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Brought forward') }}</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Closing net') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($summaryList as $sum)
                @php
                    $memberName = $sum->member->name ?? $sum->member_name ?? 'Member';
                    $meals = (float) ($sum->total_meals ?? $sum->meals ?? 0);
                    $paid = (float) ($sum->payments_received ?? $sum->bill_payments ?? $sum->paid ?? 0);
                    $broughtForward = (float) ($sum->brought_forward ?? 0);
                    $net = (float) ($sum->closing_balance ?? 0);
                @endphp
                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-5 py-3 font-semibold text-slate-900 dark:text-white">{{ $memberName }}</td>
                    <td class="px-5 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($meals, 1) }}</td>
                    <td class="px-5 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">৳{{ number_format((float) ($sum->meal_cost ?? 0), 2) }}</td>
                    <td class="px-5 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">৳{{ number_format($paid, 2) }}</td>
                    <td class="px-5 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">৳{{ number_format($broughtForward, 2) }}</td>
                    <td class="px-5 py-3 text-right tabular-nums font-bold {{ $net < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $net < 0 ? 'Owes ৳'.number_format(abs($net), 2) : 'Credit ৳'.number_format($net, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">{{ __('No member summaries recorded for this closing.') }}</td></tr>
            @endforelse
            </tbody></table>
        </div>
    </section>
</div>
@endsection