@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
        <header class="flex flex-wrap items-end justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Bill preview') }}</h1><span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Live calculation') }}</span></div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Live calculation for the selected month. Updates within 1 hour of any change.') }}</p></div><form method="GET" class="flex items-center gap-2"><select name="year" class="input w-auto text-sm">@for ($y=now()->year-1;$y<=now()->year+1;$y++)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endfor</select><select name="month" class="input w-auto text-sm">@for ($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($month===$m)>{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }}</option>@endfor</select><button type="submit" class="btn btn-dark btn-sm">{{ __('Apply') }}</button></form></header>
        <section class="space-y-5">@include('mess.bill-preview._summary',['preview'=>$preview]) @include('mess.bill-preview._row-cards',['members'=>$preview['members']])</section>
    </div>
@endsection
