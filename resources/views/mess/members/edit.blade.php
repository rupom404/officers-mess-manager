@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit member') }}</h1>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('Update profile') }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Update :name\'s information.', ['name' => $member->name]) }}</p>
        </header>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-[#111827] sm:p-6">
            <form method="POST" action="{{ route('mess.members.update', $member) }}" enctype="multipart/form-data">
                @include('mess.members._form', ['member' => $member, 'method' => 'PATCH'])
            </form>
        </section>
    </div>
@endsection
