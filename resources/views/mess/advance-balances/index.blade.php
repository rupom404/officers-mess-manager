@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-6xl space-y-5">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Member balances') }}</h1>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Live') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Each member\'s current balance — a credit they hold or an amount they owe.') }}</p>
            <p class="mt-2 max-w-3xl text-xs leading-5 text-slate-500 dark:text-slate-400">{{ __('Balances update automatically from payments and the monthly close. Use Adjust only for corrections or non-cash entries (e.g. a utility charge). For money a member hands you, record it on the Payments page.') }}</p>
        </header>
        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-[#111827]">
            @include('mess.advance-balances._list')
        </section>
    </div>
@endsection
