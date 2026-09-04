@extends('layouts.app')
@section('content')
    <div class="space-y-5">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold leading-tight tracking-tight text-slate-900">Expenses</h1>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">Bazar & fixed costs</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">All bazar and fixed expenses, most recent first.</p>
            </div>
            <a href="{{ route('mess.expenses.create') }}" class="btn btn-primary self-start sm:self-auto">Add expense</a>
        </header>

        <form method="GET" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Filters</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Narrow expenses by period and cost type.</p>
                </div>
                <a href="{{ route('mess.expenses.index') }}" class="btn btn-sm btn-ghost">Reset</a>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div><label for="period" class="block text-xs font-semibold text-slate-600">Period</label><select name="period" id="period" class="input mt-1">@foreach ($periodOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="year" class="block text-xs font-semibold text-slate-600">Year</label><select name="year" id="year" class="input mt-1">@php $currentYear=(int)($filters['year']??now()->year); $years=range(now()->year,now()->year-4); @endphp @foreach($years as $y)<option value="{{ $y }}" @selected($currentYear===(int)$y)>{{ $y }}</option>@endforeach</select></div>
                <div><label for="month" class="block text-xs font-semibold text-slate-600">Month</label><select name="month" id="month" class="input mt-1">@php $currentMonth=(int)($filters['month']??now()->month); $monthNames=[1=>'January','February','March','April','May','June','July','August','September','October','November','December']; @endphp @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($currentMonth===$m)>{{ __($monthNames[$m]) }}</option>@endfor</select></div>
                <div><label for="kind" class="block text-xs font-semibold text-slate-600">Kind</label><select name="kind" id="kind" class="input mt-1"><option value="">All types</option>@foreach(\App\Support\ExpenseKind::ALL as $k)<option value="{{ $k }}" @selected(($filters['kind']??null)===$k)>{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">Year and month apply to the Specific month and Whole year modes.</p>
                <button type="submit" class="btn btn-dark">Apply filters</button>
            </div>
        </form>

        <div class="space-y-3 md:hidden">
            @forelse ($expenses as $expense)
                <div class="expense-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-xs text-slate-500">{{ $expense->date->format('d M Y') }}</span>
                            <div class="mt-1 truncate font-semibold text-slate-900">{{ $expense->category?->name ?? '—' }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-base font-bold text-slate-900">{{ number_format((float)$expense->amount,2) }}</div>
                            <div class="mt-1"><x-status-pill :variant="$expense->category?->kind ?? 'bazar'" /></div>
                        </div>
                    </div>
                    @if($expense->description)<div class="mt-2 truncate text-sm text-slate-600">{{ $expense->description }}</div>@endif
                    @if($expense->vendor)<div class="mt-1 truncate text-xs text-slate-500">Vendor: {{ $expense->vendor }}</div>@endif
                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <div class="flex gap-3"><a href="{{ route('mess.expenses.show',$expense) }}" class="text-xs font-semibold text-emerald-700 hover:underline">View</a><a href="{{ route('mess.expenses.edit',$expense) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Edit</a></div>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No expenses recorded yet.</p>
            @endforelse
        </div>

        <div class="expense-ledger hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kind</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Vendor</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($expenses as $expense)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $expense->date->format('d M Y') }}</td>
                                <td class="px-4 py-3"><x-status-pill :variant="$expense->category?->kind ?? 'bazar'" /></td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $expense->category?->name ?? '—' }}</td>
                                <td class="max-w-[20rem] truncate px-4 py-3 text-sm text-slate-600">{{ $expense->description ?? '—' }}</td>
                                <td class="max-w-[16rem] truncate px-4 py-3 text-sm text-slate-600">{{ $expense->vendor ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-slate-900">{{ number_format((float)$expense->amount,2) }}</td>
                                <td class="px-4 py-3 text-right"><div class="inline-flex items-center gap-1">
                                    <a href="{{ route('mess.expenses.show',$expense) }}" class="btn btn-sm btn-ghost">View</a>
                                    <a href="{{ route('mess.expenses.edit',$expense) }}" class="btn btn-sm btn-ghost">Edit</a>
                                    <form method="POST" action="{{ route('mess.expenses.destroy',$expense) }}" onsubmit="return confirm('Remove this expense?');" class="inline">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-ghost text-rose-700">Delete</button></form>
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-600">No expenses recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $expenses->links() }}</div>
    </div>
@endsection