@extends('layouts.app')
@section('content')
    <div class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Members') }}</h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __(':count active', ['count' => $activeCount]) }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage member profiles, rooms, contact details, and status.') }}</p>
            </div>
            <a href="{{ route('mess.members.create') }}" class="btn btn-primary touch-target">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add member') }}
            </a>
        </header>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827]">
            <label for="member-search" class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('Search members') }}</label>
            <div class="relative">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input type="search" id="member-search" data-member-search value="{{ $search ?? '' }}" placeholder="{{ __('Search by name, mobile, email, or room…') }}" class="input pl-10" autocomplete="off" />
            </div>
        </section>

        <div data-member-list>
            @include('mess.members._list', ['members' => $members, 'activeCount' => $activeCount, 'search' => $search ?? ''])
        </div>

        @once
            <script>
                (function () {
                    const input = document.querySelector('[data-member-search]');
                    const list = document.querySelector('[data-member-list]');
                    if (!input || !list) return;
                    let timer;
                    let lastQ = '';
                    input.addEventListener('input', function () {
                        clearTimeout(timer);
                        timer = setTimeout(async function () {
                            const q = input.value.trim();
                            if (q === lastQ) return;
                            lastQ = q;
                            const res = await fetch('{{ route('mess.members.search') }}?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                            if (res.ok) list.innerHTML = await res.text();
                        }, 300);
                    });
                })();
            </script>
        @endonce
    </div>
@endsection
