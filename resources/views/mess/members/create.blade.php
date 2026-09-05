@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Add a member') }}</h1>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('New profile') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Create a new mess member. They will be set as active.') }}</p>
        </header>
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-[#111827] sm:p-6">
            <form method="POST" action="{{ route('mess.members.store') }}" enctype="multipart/form-data">
                @include('mess.members._form', ['member' => $member, 'method' => 'POST'])
            </form>
        </section>
    </div>
@endsection
