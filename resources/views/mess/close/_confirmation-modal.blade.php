@php
    $targetDate = \Carbon\Carbon::create($year ?? 2026, $month ?? 8, 1);
@endphp

<div x-cloak 
     x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-xs"
     @click.self="open = false"
     @keydown.escape.window="open = false">
    
    <div class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl transition-all dark:border-slate-800 dark:bg-[#141e33]"
         @click.stop>
        <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/50">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Confirm Month Close') }}
            </h2>
        </div>

        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('This will lock :label and write immutable financial summaries. Member closing balances (dues and advances) will automatically forward into next month.', ['label' => $targetDate->format('F Y')]) }}
        </p>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="button" 
                    @click="open = false" 
                    class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                {{ __('Cancel') }}
            </button>
            <button type="submit" 
                    class="rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-rose-700 active:scale-95">
                {{ __('Yes, close now') }}
            </button>
        </div>
    </div>
</div>