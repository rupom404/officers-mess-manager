@if ($payments->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 shadow-sm dark:border-slate-700 dark:bg-[#111827] dark:text-slate-400">
        {{ __('No payments recorded yet.') }}
    </div>
@else
    @php $canView = auth()->user()?->canManageMess() ?? false; @endphp

    <div class="space-y-3 md:hidden">
        @foreach ($payments as $payment)
            <div class="payment-card rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-[#111827]">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($canView)
                            <a href="{{ route('mess.payments.show', $payment) }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400">{{ $payment->member?->name ?? '—' }}</a>
                        @else
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $payment->member?->name ?? '—' }}</span>
                        @endif
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $payment->date->format('d M Y') }}</span>
                    </div>
                    <span class="payment-amount shrink-0 text-base font-bold text-slate-900 dark:text-white">{{ \App\Support\Money::taka($payment->amount) }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-method-pill :method="$payment->method" />
                    <x-payment-type-pill :type="$payment->type" />
                </div>
                @if ($payment->reference)
                    <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/60 dark:text-slate-400"><span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Reference') }}:</span> {{ $payment->reference }}</p>
                @endif
                @if ($canView)
                    <div class="mt-3 flex items-center justify-end gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <a href="{{ route('mess.payments.edit', $payment) }}" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('mess.payments.destroy', $payment) }}" onsubmit="return confirm('{{ __('Remove this payment?') }}');" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline dark:text-rose-400">{{ __('Delete') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-[#111827]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-[#0e1726]">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Method') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Reference') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($payments as $payment)
                        <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-semibold text-slate-900 dark:text-white">@if ($canView)<a href="{{ route('mess.payments.show', $payment) }}" class="text-emerald-700 hover:underline dark:text-emerald-400">{{ $payment->member?->name ?? '—' }}</a>@else{{ $payment->member?->name ?? '—' }}@endif</td>
                            <td class="px-5 py-3 tabular-nums text-slate-600 dark:text-slate-400">{{ $payment->date->format('d M Y') }}</td>
                            <td class="px-5 py-3"><x-payment-type-pill :type="$payment->type" /></td>
                            <td class="px-5 py-3"><x-method-pill :method="$payment->method" /></td>
                            <td class="px-5 py-3 text-right font-bold tabular-nums text-slate-900 dark:text-white">{{ \App\Support\Money::taka($payment->amount) }}</td>
                            <td class="max-w-xs truncate px-5 py-3 text-slate-600 dark:text-slate-400">{{ $payment->reference ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($canView)
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('mess.payments.edit', $payment) }}" class="btn btn-sm btn-ghost">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('mess.payments.destroy', $payment) }}" onsubmit="return confirm('{{ __('Remove this payment?') }}');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost text-rose-700 dark:text-rose-400">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="pt-1">{{ $payments->links() }}</div>
@endif
