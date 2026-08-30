@extends('layouts.pdf')

@section('title', __('Monthly Report') . ' - ' . ($period ?? ''))

@section('header')
@endsection

@section('report-body')
@php
    $members = $data['members'] ?? [];

    $formatTk = function($amount) {
        $val = (float) ($amount ?? 0);
        if ($val < -0.001) {
            return '-Tk. ' . number_format(abs($val), 2);
        }
        return 'Tk. ' . number_format($val, 2);
    };

    $totalPayments = collect($members)->sum(fn ($m) => (float) ($m['bill_payments'] ?? $m['paid'] ?? 0));
    $totalMealCost = collect($members)->sum(fn ($m) => (float) ($m['meal_cost'] ?? $m['bill'] ?? 0));
    
    // Total adjusted paid across mess
    $totalAdjustedPaid = collect($members)->sum(function ($m) {
        return (float)($m['bill_payments'] ?? $m['paid'] ?? 0) + (float)($m['brought_forward'] ?? 0);
    });

    $totalDueSum = 0;
    $totalAdvanceSum = 0;
    foreach ($members as $m) {
        $cost = (float) ($m['meal_cost'] ?? $m['bill'] ?? 0);
        $adjP = (float) ($m['bill_payments'] ?? $m['paid'] ?? 0) + (float) ($m['brought_forward'] ?? 0);
        $cNet = isset($m['closing_net']) ? (float)$m['closing_net'] : ($adjP - $cost);
        if ($cNet < -0.01) $totalDueSum += abs($cNet);
        if ($cNet > 0.01)  $totalAdvanceSum += $cNet;
    }
@endphp

<style>
    * {
        font-family: 'DejaVu Sans', sans-serif !important;
    }

    .header, header, .pdf-header, .brand-header, .report-header {
        display: none !important;
    }

    /* Top Summary Overview Table */
    .summary-card-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 18px;
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
    }
    .summary-card-table td {
        padding: 8px 12px;
        font-size: 10.5px;
        width: 33.33%;
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .summary-card-table td .label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: bold;
        display: block;
        margin-bottom: 2px;
    }
    .summary-card-table td .val {
        font-size: 12px;
        font-weight: bold;
        color: #0f172a;
    }

    /* Executive Statement Table */
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }
    .report-table th, 
    .report-table td {
        border: 1px solid #cbd5e1;
        padding: 6.5px 8px;
        font-size: 10px;
        vertical-align: middle;
    }
    
    /* Primary Tier Header (Navy) */
    .report-table thead tr.main-head th {
        background-color: #0B2038;
        color: #ffffff;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-size: 9.5px;
    }

    /* Secondary Tier Sub-Header */
    .report-table thead tr.sub-head th {
        background-color: #172b44;
        color: #e2e8f0;
        font-weight: bold;
        font-size: 9px;
        text-transform: uppercase;
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

    /* Subtitle beneath Paid */
    .sub-note {
        display: block;
        font-size: 7px;
        font-weight: normal;
        color: #cbd5e1;
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
<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 0; margin-bottom: 16px;">
    <tr>
        <td style="text-align: center; border: none; padding: 0;">
            <img src="{{ public_path('images/crest.svg') }}" width="68" height="68" style="width: 68px; height: 68px; margin: 0 auto;" />
        </td>
    </tr>
    <tr>
        <td style="text-align: center; border: none; padding-top: 6px;">
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
            <span class="val">{{ $formatTk($data['total_bazar'] ?? 0) }}</span>
        </td>
        <td>
            <span class="label">{{ __('Total Payments Collected') }}</span>
            <span class="val">{{ $formatTk($totalPayments) }}</span>
        </td>
        <td>
            @php 
                $pdfNet = collect($members)->sum(fn ($r) => ((float)($r['bill_payments'] ?? $r['paid'] ?? 0)) - ((float)($r['meal_cost'] ?? 0)));
            @endphp
            <span class="label">{{ __('Net Mess Balance') }}</span>
            <span class="val" style="color: {{ $pdfNet < 0 ? '#dc2626' : '#16a34a' }};">
                {{ ($pdfNet < 0 ? __('Owes') : __('Credit')) . ' ' . $formatTk(abs($pdfNet)) }}
            </span>
        </td>
    </tr>
</table>

<!-- Member Statement Table -->
@if (! empty($members))
    <table class="report-table">
        <thead>
            <tr class="main-head">
                <th rowspan="2" style="width: 20%; text-align: left; padding-left: 10px;">{{ __('Member') }}</th>
                <th rowspan="2" style="width: 10%; text-align: center;">{{ __('Meals') }}</th>
                <th rowspan="2" style="width: 16%; text-align: right;">{{ __('Meal Cost') }}</th>
                <th rowspan="2" style="width: 24%; text-align: right;">
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
            @foreach ($members as $index => $row)
                @php
                    $mealCost = (float) ($row['meal_cost'] ?? $row['bill'] ?? 0);
                    $rawPaid = (float) ($row['bill_payments'] ?? $row['paid'] ?? 0);
                    $broughtForward = (float) ($row['brought_forward'] ?? 0);
                    $adjustedPaid = $rawPaid + $broughtForward;

                    if (isset($row['closing_net']) && is_numeric($row['closing_net'])) {
                        $closingNet = (float) $row['closing_net'];
                    } elseif (isset($row['closing']) && is_numeric($row['closing'])) {
                        $closingNet = (float) $row['closing'];
                    } else {
                        $closingNet = $adjustedPaid - $mealCost;
                    }

                    $due = $closingNet < -0.01 ? abs($closingNet) : 0;
                    $advance = $closingNet > 0.01 ? $closingNet : 0;
                    $rowClass = ($index % 2 === 0) ? 'row-even' : 'row-odd';
                @endphp
                <tr class="{{ $rowClass }}">
                    <td style="text-align: left; font-weight: bold; color: #1e293b; padding-left: 10px;">{{ $row['name'] }}</td>
                    <td class="num" style="text-align: center; color: #334155;">{{ number_format((float) ($row['meals'] ?? 0), 1) }}</td>
                    <td class="num" style="color: #334155;">{{ $formatTk($mealCost) }}</td>
                    <td class="num" style="font-weight: 500;">
                        @if ($adjustedPaid < -0.001)
                            <span class="text-red">{{ $formatTk($adjustedPaid) }}</span>
                        @else
                            {{ $formatTk($adjustedPaid) }}
                        @endif
                    </td>
                    <td class="num">
                        @if ($due > 0)
                            <span class="text-red">{{ $formatTk($due) }}</span>
                        @else
                            <span style="color: #94a3b8;">{{ $formatTk(0) }}</span>
                        @endif
                    </td>
                    <td class="num">
                        @if ($advance > 0)
                            <span class="text-green">{{ $formatTk($advance) }}</span>
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
                <td class="num">{{ $formatTk($totalMealCost) }}</td>
                <td class="num">{{ $formatTk($totalAdjustedPaid) }}</td>
                <td class="num text-red">{{ $formatTk($totalDueSum) }}</td>
                <td class="num text-green">{{ $formatTk($totalAdvanceSum) }}</td>
            </tr>
        </tfoot>
    </table>
@endif
@endsection