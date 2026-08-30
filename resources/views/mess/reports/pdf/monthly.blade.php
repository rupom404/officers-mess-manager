@extends('layouts.pdf')

@section('title', __('Monthly Report') . ' - ' . ($period ?? ''))

@section('header')
@endsection

@section('report-body')
@php
    $members = $data['members'] ?? [];

    // Helper: Round to nearest multiple of 5 (e.g., 42 -> 40, 43.5 -> 45)
    $round5 = function ($val) {
        return (float) (round(((float)$val) / 5) * 5);
    };

    // Helper: Format Tk without decimals
    $formatTk = function($amount) use ($round5) {
        $val = $round5($amount);
        if ($val < -0.001) {
            return '-Tk. ' . number_format(abs($val), 0);
        }
        return 'Tk. ' . number_format($val, 0);
    };

    // Summary Totals
    $rawTotalBazar = (float) ($data['total_bazar'] ?? 0);
    $rawTotalPayments = collect($members)->sum(fn ($m) => (float) ($m['bill_payments'] ?? $m['paid'] ?? 0));
    
    $totalBazar = $round5($rawTotalBazar);
    $totalPayments = $round5($rawTotalPayments);

    // Exact Net Mess Balance: Total Payments - Total Bazar
    $netMessBalance = $totalPayments - $totalBazar;

    // Process each member with reconciled rounding
    $processedMembers = [];
    $totalMealCostSum = 0;
    $totalAdjustedPaidSum = 0;
    $totalDueSum = 0;
    $totalAdvanceSum = 0;

    foreach ($members as $row) {
        $meals = (float) ($row['meals'] ?? 0);
        $mealCost = $round5($row['meal_cost'] ?? $row['bill'] ?? 0);
        $rawPaid = (float) ($row['bill_payments'] ?? $row['paid'] ?? 0);
        $broughtForward = (float) ($row['brought_forward'] ?? 0);
        
        // Paid after adding credit (+) or subtracting owes (-)
        $adjustedPaid = $round5($rawPaid + $broughtForward);
        
        // Closing Net Balance
        $closingNet = $adjustedPaid - $mealCost;
        $due = $closingNet < -0.01 ? abs($closingNet) : 0;
        $advance = $closingNet > 0.01 ? $closingNet : 0;

        $totalMealCostSum += $mealCost;
        $totalAdjustedPaidSum += $adjustedPaid;
        $totalDueSum += $due;
        $totalAdvanceSum += $advance;

        $processedMembers[] = [
            'name'          => $row['name'],
            'meals'         => $meals,
            'meal_cost'     => $mealCost,
            'adjusted_paid' => $adjustedPaid,
            'due'           => $due,
            'advance'       => $advance,
        ];
    }
@endphp

<style>
    * {
        font-family: 'DejaVu Sans', sans-serif !important;
    }

    .header, header, .pdf-header, .brand-header, .report-header {
        display: none !important;
    }

    /* Summary Header Table */
    .summary-card-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
    }
    .summary-card-table td {
        padding: 7px 10px;
        font-size: 10px;
        width: 33.33%;
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .summary-card-table td .label {
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: bold;
        display: block;
        margin-bottom: 2px;
    }
    .summary-card-table td .val {
        font-size: 11.5px;
        font-weight: bold;
        color: #0f172a;
    }

    /* Statement Table */
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    .report-table th, 
    .report-table td {
        border: 1px solid #cbd5e1;
        padding: 6px 8px;
        font-size: 10px;
        vertical-align: middle;
        font-family: 'DejaVu Sans', sans-serif !important;
    }
    
    /* Tier 1 Navy Header */
    .report-table thead tr.main-head th {
        background-color: #0B2038;
        color: #ffffff;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-size: 9.5px;
        text-align: center;
    }

    /* Tier 2 Sub Header */
    .report-table thead tr.sub-head th {
        background-color: #172b44;
        color: #e2e8f0;
        font-weight: bold;
        font-size: 9px;
        text-transform: uppercase;
        text-align: right;
    }

    .report-table td.num {
        text-align: right;
    }

    /* Zebra Striping */
    .report-table tbody tr.row-even {
        background-color: #ffffff;
    }
    .report-table tbody tr.row-odd {
        background-color: #f8fafc;
    }

    /* Crisp Sub-Note under Paid */
    .sub-note {
        display: block;
        font-size: 8px;
        font-weight: normal;
        color: #93c5fd;
        margin-top: 2px;
        text-transform: none;
        letter-spacing: 0;
    }

    /* Summary Footer */
    .report-table tfoot tr td {
        background-color: #f1f5f9;
        font-weight: bold;
        border-top: 2px solid #0B2038;
        color: #0f172a;
        font-size: 10px;
    }

    .text-red {
        color: #dc2626;
        font-weight: bold;
    }
    .text-green {
        color: #16a34a;
        font-weight: bold;
    }
</style>

<!-- Brand Header -->
<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 0; margin-bottom: 14px;">
    <tr>
        <td style="text-align: center; border: none; padding: 0;">
            <img src="{{ public_path('images/crest.svg') }}" width="64" height="64" style="width: 64px; height: 64px; margin: 0 auto;" />
        </td>
    </tr>
    <tr>
        <td style="text-align: center; border: none; padding-top: 5px;">
            <h2 style="margin: 0; font-size: 18px; font-weight: bold; color: #0B2038; letter-spacing: 1.5px; text-transform: uppercase;">
                OFFICERS' MESS
            </h2>
            <p style="margin: 2px 0 0 0; font-size: 11px; color: #475569; font-weight: 500;">
                Monthly Statement Report — {{ $data['month_name'] ?? 'August' }} {{ $data['year'] ?? '2026' }}
            </p>
        </td>
    </tr>
</table>

<!-- Summary Info Cards Box -->
<table class="summary-card-table">
    <tr>
        <td>
            <span class="label">{{ __('Total Members') }}</span>
            <span class="val">{{ count($members) }}</span>
        </td>
        <td>
            <span class="label">{{ __('Total Meals') }}</span>
            <span class="val">{{ number_format((float) ($data['total_meals'] ?? 0), 1) }}</span>
        </td>
        <td>
            <span class="label">{{ __('Current Meal Rate') }}</span>
            <span class="val">{{ $formatTk($data['meal_rate'] ?? 0) }} <span style="font-size: 9px; font-weight: normal; color: #64748b;">/ meal</span></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">{{ __('Total Bazar Expenditure') }}</span>
            <span class="val">{{ $formatTk($totalBazar) }}</span>
        </td>
        <td>
            <span class="label">{{ __('Total Payments Collected') }}</span>
            <span class="val">{{ $formatTk($totalPayments) }}</span>
        </td>
        <td>
            <span class="label">{{ __('Net Mess Balance') }}</span>
            <span class="val" style="color: {{ $netMessBalance < 0 ? '#dc2626' : '#16a34a' }};">
                {{ ($netMessBalance < 0 ? __('Owes') : __('Credit')) . ' ' . $formatTk(abs($netMessBalance)) }}
            </span>
        </td>
    </tr>
</table>

<!-- Member Statement Table -->
@if (! empty($processedMembers))
    <table class="report-table">
        <thead>
            <tr class="main-head">
                <th rowspan="2" style="width: 21%; text-align: left; padding-left: 10px;">{{ __('Member') }}</th>
                <th rowspan="2" style="width: 10%; text-align: center;">{{ __('Meals') }}</th>
                <th rowspan="2" style="width: 15%; text-align: right;">{{ __('Meal Cost') }}</th>
                <th rowspan="2" style="width: 24%; text-align: center;">
                    {{ __('Paid') }}
                    <span class="sub-note">(After calculating owes/credit)</span>
                </th>
                <th colspan="2" style="width: 30%; text-align: center;">Closing (Net)</th>
            </tr>
            <tr class="sub-head">
                <th style="width: 15%; text-align: right;">{{ __('Due') }}</th>
                <th style="width: 15%; text-align: right;">{{ __('Advance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($processedMembers as $index => $row)
                @php
                    $rowClass = ($index % 2 === 0) ? 'row-even' : 'row-odd';
                @endphp
                <tr class="{{ $rowClass }}">
                    <td style="text-align: left; font-weight: bold; color: #1e293b; padding-left: 10px;">{{ $row['name'] }}</td>
                    <td class="num" style="text-align: center; color: #334155;">{{ number_format((float) $row['meals'], 1) }}</td>
                    <td class="num" style="color: #334155;">{{ $formatTk($row['meal_cost']) }}</td>
                    <td class="num" style="color: #334155;">
                        @if ($row['adjusted_paid'] < -0.001)
                            <span class="text-red">{{ $formatTk($row['adjusted_paid']) }}</span>
                        @else
                            {{ $formatTk($row['adjusted_paid']) }}
                        @endif
                    </td>
                    <td class="num">
                        @if ($row['due'] > 0)
                            <span class="text-red">{{ $formatTk($row['due']) }}</span>
                        @else
                            <span style="color: #94a3b8;">{{ $formatTk(0) }}</span>
                        @endif
                    </td>
                    <td class="num">
                        @if ($row['advance'] > 0)
                            <span class="text-green">{{ $formatTk($row['advance']) }}</span>
                        @else
                            <span style="color: #94a3b8;">{{ $formatTk(0) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align: left; padding-left: 10px;">{{ __('Total') }}</td>
                <td style="text-align: center;">{{ number_format((float) ($data['total_meals'] ?? 0), 1) }}</td>
                <td class="num">{{ $formatTk($totalMealCostSum) }}</td>
                <td class="num">{{ $formatTk($totalAdjustedPaidSum) }}</td>
                <td class="num text-red">{{ $formatTk($totalDueSum) }}</td>
                <td class="num text-green">{{ $formatTk($totalAdvanceSum) }}</td>
            </tr>
        </tfoot>
    </table>
@endif
@endsection