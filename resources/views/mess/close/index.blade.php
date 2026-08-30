@extends('layouts.app')

@section('content')
@php
    $year = $year ?? 2026;
    $month = $month ?? 8;
    $periodLabel = \Carbon\Carbon::create($year, $month, 1)->format('F Y');
@endphp

<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            {{ __('Close month') }}
        </h1>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ __('Snapshot the mess for :period.', ['period' => $periodLabel]) }}
        </p>
    </header>

    <!-- Stat Overview Summary -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL BAZAR') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($totalBazar ?? 0), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL MEALS') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ number_format((float) ($totalMeals ?? 0), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('MEAL RATE') }}</span>
            <p class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">৳{{ number_format((float) ($mealRate ?? 0), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('TOTAL FIXED') }}</span>
            <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">৳{{ number_format((float) ($totalFixed ?? 0), 2) }}</p>
        </div>
    </div>

    <!-- Month Close Trigger Form -->
    <div class="pt-2">
        <form id="close-month-form" method="POST" action="{{ Route::has('mess.close.store') ? route('mess.close.store') : url('/mess/close') }}">
            @csrf
            
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">

            <button type="button" 
                    id="open-close-modal-btn"
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-md transition-all hover:bg-emerald-700 active:scale-95">
                {{ __('Close :period', ['period' => $periodLabel]) }}
            </button>
        </form>
    </div>
</div>

<!-- Standalone Confirmation Modal -->
<div id="close-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-xs">
    <div class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl transition-all dark:border-slate-800 dark:bg-[#141e33]">
        <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/50">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Confirm Month Close') }}
            </h2>
        </div>

        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('This will lock :label and generate immutable financial summaries. Member closing balances (dues and advances) will automatically forward into next month.', ['label' => $periodLabel]) }}
        </p>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="button" 
                    id="cancel-close-modal-btn"
                    class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                {{ __('Cancel') }}
            </button>
            <button type="button" 
                    id="submit-close-modal-btn"
                    class="rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-rose-700 active:scale-95">
                {{ __('Yes, close now') }}
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('close-confirm-modal');
        const openBtn = document.getElementById('open-close-modal-btn');
        const cancelBtn = document.getElementById('cancel-close-modal-btn');
        const submitBtn = document.getElementById('submit-close-modal-btn');
        const form = document.getElementById('close-month-form');

        if (openBtn && modal) {
            openBtn.addEventListener('click', function () {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        }

        if (cancelBtn && modal) {
            cancelBtn.addEventListener('click', function () {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });
        }

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });
        }

        if (submitBtn && form) {
            submitBtn.addEventListener('click', function () {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Closing...';
                form.submit();
            });
        }
    });
</script>
@endsection