@extends('layouts.app')
@section('content')
    @php
        use App\Models\MonthlyClosing;
        use App\Support\Money;
        use Carbon\Carbon;

        $now = Carbon::now();
        $currentClosing = MonthlyClosing::query()
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->first();
        $cards = $cards ?? [
            'total_members' => 0, 'today_meals' => 0.0,
            'total_meals' => 0.0,
            'monthly_expenses' => 0.0, 'meal_rate' => 0.0,
            'total_credit' => 0.0, 'total_dues' => 0.0,
            'total_member_balance' => 0.0,
        ];
        $pendingMealOff = $pendingMealOff ?? 0;
    @endphp

    <div class="space-y-6">
        <header>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Dashboard') }}
            </h1>
            <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
            </p>
        </header>

        @if ($currentClosing)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50/80 p-4 text-sm text-amber-900 shadow-xs dark:border-amber-800/80 dark:bg-amber-950/40 dark:text-amber-300">
                <div>
                    <p class="font-bold">{{ __('MONTH CLOSED — :label is locked.', ['label' => $now->format('F Y')]) }}</p>
                    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-400">{{ __('Meal/expense/payment writes for this month are disabled. Use corrections to adjust a closed month.') }}</p>
                </div>
                <a href="{{ route('mess.closings.show', $currentClosing) }}" class="inline-flex items-center rounded-xl border border-amber-300 bg-white px-3.5 py-1.5 text-xs font-bold text-amber-900 shadow-xs transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-[#141e33] dark:text-amber-300 dark:hover:bg-[#1a2942]">
                    {{ __('View closing') }}
                </a>
            </div>
        @endif

        @if ($pendingMealOff > 0)
            <a href="{{ route('mess.meal-off.index') }}" class="block rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900 shadow-xs transition-all hover:bg-amber-100 dark:border-amber-800/80 dark:bg-amber-950/40 dark:text-amber-300">
                {{ trans_choice(':count pending meal off request awaiting approval|:count pending meal off requests awaiting approval', $pendingMealOff) }}
            </a>
        @endif

        <!-- Uniform 7-Card Grid with Dedicated Adaptive Badges -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            
            <!-- 1. Total Members -->
            <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dashboard-card dark:border-[#1e2c47] dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">{{ __('Total Members') }}</span>
                    <div class="stat-icon-badge badge-slate">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ number_format((int) $cards['total_members']) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Active participants') }}</span>
            </div>

            <!-- 2. Today's Meals -->
            <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dashboard-card dark:border-[#1e2c47] dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">{{ __("Today's Meals") }}</span>
                    <div class="stat-icon-badge badge-sky">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ number_format((float) $cards['today_meals'], 1) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Logged today') }}</span>
            </div>

            <!-- 3. Total Meals (Month) -->
            <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dashboard-card dark:border-[#1e2c47] dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">{{ __('Total Meals (Month)') }}</span>
                    <div class="stat-icon-badge badge-indigo">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ number_format((float) ($cards['total_meals'] ?? 0), 1) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Cumulative monthly count') }}</span>
            </div>

            <!-- 4. Current Meal Rate -->
            <div class="group relative rounded-2xl border border-emerald-300/80 bg-white p-5 shadow-xs transition-all duration-200 dashboard-card is-positive hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-500/30 dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">{{ __('Meal Rate') }}</span>
                    <div class="stat-icon-badge badge-emerald">
                        <span class="text-sm font-black">৳</span>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-emerald-700 dark:text-emerald-400">
                    {{ Money::taka((float) $cards['meal_rate']) }}
                    <span class="text-xs font-bold text-slate-400 dark:text-slate-500">/ meal</span>
                </p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ ((float) $cards['meal_rate'] === 0.0) ? __('no bazar recorded') : __('Live average rate') }}</span>
            </div>

            <!-- 5. Monthly Expenses -->
            <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dashboard-card is-positive dark:border-[#1e2c47] dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">{{ __('Monthly Expenses') }}</span>
                    <div class="stat-icon-badge badge-amber">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ Money::taka((float) $cards['monthly_expenses']) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Bazar & fixed expenditures') }}</span>
            </div>

            <!-- 6. Total Credit (Advance) -->
            <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dashboard-card dark:border-[#1e2c47] dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">{{ __('Total Advance') }}</span>
                    <div class="stat-icon-badge badge-emerald">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400">{{ Money::taka((float) ($cards['total_credit'] ?? 0)) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Prepaid wallet balances') }}</span>
            </div>

            <!-- 7. Total Dues -->
            <div class="group relative rounded-2xl border border-rose-300/80 bg-white p-5 shadow-xs transition-all duration-200 dashboard-card is-negative hover:-translate-y-0.5 hover:shadow-md dark:border-rose-500/30 dark:bg-[#141e33]">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">{{ __('Total Dues') }}</span>
                    <div class="stat-icon-badge badge-rose">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-rose-600 dark:text-rose-400">{{ Money::taka((float) ($cards['total_dues'] ?? 0)) }}</p>
                <span class="mt-1 block text-xs font-medium text-slate-400 dark:text-slate-500">{{ __('Owed by members') }}</span>
            </div>
        </section>

        <!-- Widgets & Charts Section -->
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Members with dues -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2c47] dark:bg-[#141e33]">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Members with dues') }}</h3>
                @if (empty($membersWithDues))
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No one currently owes the mess. 🎉') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-[#1e2c47]">
                        @foreach ($membersWithDues as $m)
                            <li class="flex items-center justify-between py-2.5">
                                <a href="{{ route('mess.members.wallet', $m['id']) }}" class="text-sm font-semibold text-slate-900 hover:text-emerald-600 dark:text-slate-200 dark:hover:text-emerald-400">{{ $m['name'] }}</a>
                                <span class="text-sm font-black text-rose-600 dark:text-rose-400">{{ Money::taka(abs($m['net'])) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- Top eaters -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2c47] dark:bg-[#141e33]">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Top eaters this month') }}</h3>
                @if (empty($topEaters))
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No meals recorded yet this month.') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-[#1e2c47]">
                        @foreach ($topEaters as $m)
                            <li class="flex items-center justify-between py-2.5">
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-200">{{ $m['name'] }}</span>
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ number_format((float) $m['meals'], 1) }} {{ __('meals') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- Spend vs collection -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2c47] dark:bg-[#141e33]">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Spend vs collection this month') }}</h3>
                <div style="height: 240px;">
                    <canvas id="bazar-collection-chart"></canvas>
                </div>
            </div>

            <!-- Expense categories -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2c47] dark:bg-[#141e33]">
                <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Expense categories this month') }}</h3>
                @if (empty($expenseCategoryMix))
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No expenses recorded yet this month.') }}</p>
                @else
                    <div style="height: 240px;">
                        <canvas id="expense-category-chart"></canvas>
                    </div>
                @endif
            </div>
        </section>

        @once
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    window.initDashboardChart('bazar-collection-chart', {
                        type: 'bar',
                        data: {
                            labels: [@json([__('Spend'), __('Collected')])],
                            datasets: [{
                                label: '@lang('Amount')',
                                data: [@json([(float) ($bazarVsCollection['spend'] ?? 0), (float) ($bazarVsCollection['collected'] ?? 0)])],
                                backgroundColor: ['#f43f5e', '#10b981'],
                                borderRadius: 6,
                            }],
                        },
                    });
                    @if (! empty($expenseCategoryMix))
                        window.initDashboardChart('expense-category-chart', {
                            type: 'doughnut',
                            data: {
                                labels: @json(collect($expenseCategoryMix)->pluck('label')),
                                datasets: [{
                                    data: @json(collect($expenseCategoryMix)->pluck('amount')),
                                    backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#f43f5e', '#64748b', '#06b6d4', '#ec4899'],
                                    borderWidth: 2,
                                    borderColor: document.documentElement.classList.contains('dark') ? '#141e33' : '#ffffff',
                                }],
                            },
                        });
                    @endif
                });
            </script>
        @endonce
    </div>
@endsection