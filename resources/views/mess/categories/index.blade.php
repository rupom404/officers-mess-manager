@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Expense categories') }}</h1>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('Management') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage the categories used for bazar and fixed expenses. Default categories cannot be deleted.') }}</p>
        </header>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-[#111827] lg:col-span-2">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50/80 dark:bg-slate-900/60"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Kind') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Default') }}</th>
                            <th class="relative px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($categories as $cat)
                                <tr class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-900/50">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $cat->name }}</td>
                                    <td class="px-4 py-3 text-sm"><x-status-pill :variant="$cat->kind" /></td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $cat->is_default ? __('Yes') : __('No') }}</td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        @if (! $cat->is_default)
                                            <a href="{{ route('mess.categories.edit', $cat) }}" class="font-medium text-emerald-700 hover:text-emerald-800 hover:underline dark:text-emerald-400">{{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('mess.categories.destroy', $cat) }}" class="ml-3 inline" onsubmit="return confirm('{{ __('Delete this category?') }}');">@csrf @method('DELETE')<button type="submit" class="font-medium text-rose-700 hover:underline dark:text-rose-400">{{ __('Delete') }}</button></form>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-600">{{ __('Locked') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No categories yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-[#111827] sm:p-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Add category') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Create a reusable category for expense entries.') }}</p>
                <form method="POST" action="{{ route('mess.categories.store') }}" class="mt-4">@csrf @include('mess.categories._form')</form>
            </section>
        </div>
    </div>
@endsection
