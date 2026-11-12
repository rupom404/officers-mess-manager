@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            {{ __('Edit guest meal') }}
        </h1>
    </header>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-[#111827]">
        <form method="POST" action="{{ route('mess.guest-meals.update', [$guestMeal->id]) }}">
            @csrf
            @method('PUT')
            
            @include('mess.guest-meals._form')

            <div class="mt-6 flex items-center gap-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition-all hover:bg-emerald-700 active:scale-95">
                    {{ __('Save guest meal') }}
                </button>
                <a href="{{ route('mess.guest-meals.index') }}" class="text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Danger Zone / Delete -->
    <div class="mt-8 rounded-2xl border border-rose-100 bg-rose-50/50 p-6 dark:border-rose-900/30 dark:bg-rose-950/20">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-rose-800 dark:text-rose-400">{{ __('Delete this guest meal') }}</h3>
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300/70">
                    {{ __('Permanently removes this meal from the ledger and automatically deducts it from the host\'s total.') }}
                </p>
            </div>
            
            <form action="{{ route('mess.guest-meals.destroy', [$guestMeal->id]) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to permanently delete this guest meal?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:bg-rose-700 active:scale-95">
                    {{ __('Delete meal') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection