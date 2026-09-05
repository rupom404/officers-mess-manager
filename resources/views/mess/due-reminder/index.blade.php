@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-5xl space-y-5">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Due reminders') }}</h1>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ __('Action needed') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Send an in-app reminder to every member who currently owes money.') }}</p>
        </header>
        <form method="POST" action="{{ route('mess.due-reminder.send') }}" class="space-y-3 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-[#111827] sm:p-6">
            @csrf
            @if ($members->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
                    {{ __('No members currently have a due balance.') }}
                </p>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($members as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <label class="flex min-w-0 items-center gap-3 text-sm text-slate-800 dark:text-slate-200">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" checked class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="font-medium text-slate-900 dark:text-white">{{ $member->name }}</span>
                                @if ($member->mobile)
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $member->mobile }}</span>
                                @endif
                            </label>
                            @php $net = $member->advanceBalance?->netBalance() ?? 0; @endphp
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ __('Owes') }} {{ \App\Support\Money::taka(abs($net)) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="btn btn-primary">{{ __('Send reminders') }}</button>
                </div>
            @endif
        </form>
    </div>
@endsection
