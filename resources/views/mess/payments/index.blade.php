@extends('layouts.app')
@section('content')
    <div class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Payments') }}</h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Financial ledger') }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Record and review all mess payments.') }}</p>
            </div>
            <a href="{{ route('mess.payments.create') }}" class="btn btn-primary touch-target">{{ __('Record payment') }}</a>
        </header>

        <form method="GET" class="payments-filter grid grid-cols-1 gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827] sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="member_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Member') }}</label>
                <select name="member_id" id="member_id" class="input mt-1">
                    <option value="">{{ __('All members') }}</option>
                    @foreach ($members as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['member_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="method" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Method') }}</label>
                <select name="method" id="method" class="input mt-1">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach (\App\Support\PaymentMethod::ALL as $m)
                        <option value="{{ $m }}" @selected(($filters['method'] ?? null) === $m)>{{ \App\Support\PaymentMethod::LABELS[$m] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="period" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Period') }}</label>
                <select name="period" id="period" class="input mt-1">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="year" class="block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Year / month') }}</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    @php $currentYear=(int)($filters['year']??now()->year); $years=range(now()->year,now()->year-4); @endphp
                    <select name="year" id="year" class="input">
                        @foreach ($years as $y)<option value="{{ $y }}" @selected($currentYear===(int)$y)>{{ $y }}</option>@endforeach
                    </select>
                    <select name="month" id="month" class="input">
                        @php $currentMonth=(int)($filters['month']??now()->month); $monthNames=[1=>'January','February','March','April','May','June','July','August','September','October','November','December']; @endphp
                        @for ($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($currentMonth===$m)>{{ __($monthNames[$m]) }}</option>@endfor
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 sm:col-span-2 lg:col-span-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Year and month apply to the Specific month and Whole year modes.') }}</p>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-dark touch-target">{{ __('Filter') }}</button>
                    <a href="{{ route('mess.payments.index') }}" class="btn btn-ghost touch-target">{{ __('Reset') }}</a>
                </div>
            </div>
        </form>

        @include('mess.payments._list')
    </div>
@endsection