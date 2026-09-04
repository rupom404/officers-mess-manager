@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use Carbon\Carbon;
        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $isSnapshot = ($data['source'] ?? 'live') === 'snapshot';
        $members = $data['members'] ?? [];
        $totalNet = $isSnapshot ? collect($members)->sum(fn ($r) => ($r['advance_balance'] ?? 0) - ($r['due_balance'] ?? 0)) : collect($members)->sum(fn ($r) => ($r['advance_balance'] ?? 0) + ($r['bill_payments'] ?? 0) - ($r['bill'] ?? 0) - ($r['due_balance'] ?? 0));
        $hasData = ! empty($members);
    @endphp
    <div class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold leading-tight tracking-tight text-slate-900">{{ __('Monthly Report') }}</h1>
                    <span class="inline-flex items-center rounded-full {{ $isSnapshot ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }} px-2.5 py-1 text-xs font-bold">{{ $isSnapshot ? __('Closed month') : __('Live') }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $period }}</p>
            </div>
        </header>
        <x-report-toolbar route="mess.reports.monthly" :year="$year" :month="$month" showExports="true" :from="$monthRange['first'] ?? null" :to="$monthRange['last'] ?? null" :filters="request()->query('from') || request()->query('to') || request()->query('category_id') || request()->query('month') ? request()->only(['from','to','category_id','month']) : []" />
        @if (! $hasData)
            <x-empty-state :title="__('No data for :month yet', ['month' => $period])" :description="__('Once meals, bazar, or fixed expenses are entered for this month, the report will appear here.')" />
        @else
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([['Members',count($members)],['Meals',number_format((float)$data['total_meals'],1)],['Meal rate',Money::taka($data['meal_rate']) . ' / meal'],['Total bazar',Money::taka($data['total_bazar'])],['Total fixed',Money::taka($data['total_fixed'])],['Balance (net)',($totalNet<0?'Owes ':'Credit ').Money::taka(abs($totalNet))]] as $card)
                    <div class="dashboard-card rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __($card[0]) }}</p>
                        <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">{{ $card[1] }}</p>
                    </div>
                @endforeach
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">{{ __('Member balances') }}</h2><p class="mt-0.5 text-xs text-slate-500">{{ __('Current month activity, brought forward, and closing position.') }}</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Member') }}</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meals') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Bill') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Paid') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Due') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Brought forward') }}</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Closing (net)') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($members as $row)
                            @php $bf=(float)($row['brought_forward']??0); $net=$isSnapshot?(($row['advance_balance']??0)-($row['due_balance']??0)):(($row['advance_balance']??0)+($row['bill_payments']??0)-($row['bill']??0)-($row['due_balance']??0)); @endphp
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold"><a href="{{ route('mess.reports.member-statement',['member_id'=>$row['member_id'],'year'=>$year,'month'=>$month]) }}" class="text-emerald-700 hover:underline">{{ $row['name'] }}</a></td>
                                <td class="px-4 py-3 text-xs font-semibold">{{ __(ucfirst($row['status']??'active')) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float)$row['meals'],1) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['bill']??0) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['bill_payments']??0) }}</td><td class="px-4 py-3 text-right tabular-nums text-rose-600">{{ Money::taka($row['due']??0) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $bf<0?'text-rose-600':($bf>0?'text-emerald-600':'text-slate-500') }}">{{ $bf>0?'Credit ':($bf<0?'Owes ':'') }}{{ Money::taka(abs($bf)) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-bold {{ $net<0?'text-rose-600':'text-emerald-600' }}">{{ $net<0?'Owes ':'Credit ' }}{{ Money::taka(abs($net)) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="text-xs text-slate-500">@lang('Brought forward = the member\'s net position carried in from before this month. This month = bill, payments, and resulting due. Closing (net) = the member\'s true running balance.')</p>
        @endif
    </div>
@endsection