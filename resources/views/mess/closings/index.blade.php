@extends('layouts.app')
@section('content')
    <div class="space-y-5">
        <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Month closings') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Locked monthly records and their final meal rates.') }}</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $closings->total() }} {{ __('records') }}</span>
        </header>

        @if ($closings->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm dark:border-slate-700 dark:bg-[#111827]">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('No months have been closed yet.') }}</p>
            </div>
        @else
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-[#111827]">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Closing history') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Open a month to view its immutable closing snapshot.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-[#0e1726]"><tr class="border-b border-slate-200 dark:border-slate-800">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Period') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total bazar') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meal rate') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Members') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Closed at') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Closed by') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($closings as $c)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-5 py-3"><a href="{{ route('mess.closings.show', $c) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ \Carbon\Carbon::create($c->year, $c->month, 1)->format('F Y') }}</a><span class="ml-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Locked') }}</span></td>
                                    <td class="px-5 py-3 text-right font-medium tabular-nums text-slate-800 dark:text-slate-200">{{ \App\Support\Money::taka($c->total_bazar) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ \App\Support\Money::taka($c->meal_rate) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ $c->member_count }}</td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ $c->closed_at?->format('d M Y, H:i') ?? '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-400">{{ $c->closedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="pt-1">{{ $closings->links() }}</div>
        @endif
    </div>
@endsection
