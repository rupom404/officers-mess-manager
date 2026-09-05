@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-6xl space-y-5">
        <header><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Meal off approval') }}</h1><span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('Requests') }}</span></div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Review and approve or reject meal off requests.') }}</p></header>
        @php $tabs=[['key'=>'pending','label'=>__('Pending (:n)',['n'=>$counts['pending']??0]),'url'=>route('mess.meal-off.index',['tab'=>'pending'])],['key'=>'approved','label'=>__('Approved (:n)',['n'=>$counts['approved']??0]),'url'=>route('mess.meal-off.index',['tab'=>'approved'])],['key'=>'rejected','label'=>__('Rejected (:n)',['n'=>$counts['rejected']??0]),'url'=>route('mess.meal-off.index',['tab'=>'rejected'])]]; @endphp
        <x-tab-nav :tabs="$tabs" :active-key="$tab" class="mb-6" />
        <section class="space-y-3">@forelse ($requests as $req) @include('mess.meal-off._card',['request'=>$req]) @empty <x-empty-state :title="__('No :status meal off requests.',['status'=>$tab])" :description="__('When members request meal off, they will appear here.')" /> @endforelse</section>
        <div class="mt-4">{{ $requests->links() }}</div>
    </div>
@endsection
