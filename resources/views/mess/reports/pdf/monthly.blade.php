@extends('layouts.pdf')

@section('title', __('Monthly Report') . ' - ' . ($period ?? ''))

{{-- Suppress default header from parent layout to fix double header --}}
@section('header')
@endsection

@section('report-body')
@php
    $members = $data['members'] ?? [];

    // Currency helper for PDF
    $formatTk = function($amount) {
        $val = (float) ($amount ?? 0);
        if ($val < -0.001) {
            return '-Tk. ' . number_format(abs($val), 2);
        }
        return 'Tk. ' . number_format($val, 2);
    };

    // Calculate total payments across all members
    $totalPayments = collect($members)->sum(fn ($m) => (float) ($m['bill_payments'] ?? $m['paid'] ?? 0));
@endphp

<style>
    /* Force clean font */
    * {
        font-family: 'DejaVu Sans', sans-serif !important;
    }

    /* Hide parent layout header elements if rendered outside section */
    .header, header, .pdf-header, .brand-header, .report-header {
        display: none !important;
    }

    /* Center title header in upper middle */
    .report-title-header {
        text-align: center;
        margin-top: 10px;
        margin-bottom: 20px;
        width: 100%;
    }
    .report-title-header h2 {
        margin: 0 0 4px 0;
        font-size: 20px;
        color: #111;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .report-title-header p {
        margin: 0;
        font-size: 12px;
        color: #444;
    }

    /* Summary totals box table */
    .totals-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        background-color: #fafafa;
    }
    .totals-table td {
        padding: 8px 10px;
        font-size: 11px;
        width: 33.33%;
        border: 1px solid #444444;
    }

    /* Main Table Styles */
    .pdf-table-compact {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .pdf-table-compact th, .pdf-table-compact td {
        border: 1px solid #444444;
        padding: 6px 7px;
        font-size: 10.5px;
        vertical-align: middle;
    }
    .pdf-table-compact thead th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
    }
    .pdf-table-compact td.num {
        text-align: right;
    }

    /* Sub-note under Paid column header */
    .sub-note {
        display: block;
        font-size: 7.5px;
        font-weight: normal;
        color: #555555;
        margin-top: 2px;
        line-height: 1.1;
        text-transform: none;
    }

    /* Color Helpers */
    .text-red {
        color: #dc3545;
        font-weight: bold;
    }
    .text-green {
        color: #198754;
        font-weight: bold;
    }
</style>

<!-- Upper Middle Header (Table-based layout to prevent DomPDF distortion & overlap) -->
<table style="width: 100%; border: none; border-collapse: collapse; margin-top: 5px; margin-bottom: 18px;">
    <tr>
        <td style="text-align: center; border: none; padding: 0;">
            <img src="{{ public_path('images/crest.svg') }}" width="72" height="72" style="width: 72px; height: 72px; margin: 0 auto;" />
        </td>
    </tr>
    <tr>
        <td style="text-align: center; border: none; padding-top: 8px;">
            <h2 style="margin: 0; font-size: 19px; font-weight: bold; color: #0B2038; letter-spacing: 1.2px; text-transform: uppercase;">
                OFFICERS' MESS
            </h2>
            <p style="margin: 3px 0 0 0; font-size: 11.5px; color: #444;">
                Monthly Report — {{ $data['month_name'] ?? 'August' }} {{ $data['year'] ?? '2026' }}
            </p>
        </td>
    </tr>
</table>

<!-- Summary Grid -->
<table class="totals-table">
    <tr>
        <td><strong>{{ __('Members') }}:</strong> {{ count($members) }}</td>
        <td><strong>{{ __('Meals') }}:</strong> {{ number_format((float) ($data['total_meals'] ?? 0), 1) }}</td>
        <td><strong>{{ __('Meal rate') }}:</strong> {{ $formatTk($data['meal_rate'] ?? 0) }} / meal</td>
    </tr>
    <tr>
        <td><strong>{{ __('Total bazar') }}:</strong> {{ $formatTk($data['total_bazar'] ?? 0) }}</td>
        <td><strong>{{ __('Total Payments') }}:</strong> {{ $formatTk($totalPayments) }}</td>
        <td>
            @php 
                $pdfNet = collect($members)->sum(fn ($r) => ((float)($r['bill_payments'] ?? $r['paid'] ?? 0)) - ((float)($r['meal_cost'] ?? $r['bill'] ?? 0)));
            @endphp
            <strong>{{ __('Balance (net)') }}:</strong> {{ ($pdfNet < 0 ? __('Owes') : __('Credit')) . ' ' . $formatTk(abs($pdfNet)) }}
        </td>
    </tr>
</table>

<!-- Member Statement Table -->
@if (! empty($members))
    <table class="pdf-table-compact">
        <thead>
            <tr>
                <th rowspan="2" style="width: 20%; text-align: left;">{{ __('Member') }}</th>
                <th rowspan="2" style="width: 10%; text-align: center;">{{ __('Meals') }}</th>
                <th rowspan="2" style="width: 16%; text-align: right;">{{ __('Meal cost') }}</th>
                <th rowspan="2" style="width: 24%; text-align: right;">
                    {{ __('Paid') }}
                    <span class="sub-note">(After calculating owes/credit)</span>
                </th>
                <th colspan="2" style="width: 30%; text-align: center;">Closing (Net)</th>
            </tr>
            <tr>
                <th style="width: 15%; text-align: right;">{{ __('Due') }}</th>
                <th style="width: 15%; text-align: right;">{{ __('Advance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $row)
                @php
                    $mealCost = (float) ($row['meal_cost'] ?? $row['bill'] ?? 0);
                    $rawPaid = (float) ($row['bill_payments'] ?? $row['paid'] ?? 0);
                    $broughtForward = (float) ($row['brought_forward'] ?? 0);
                    
                    // Paid after adjusting Brought Forward (Credit [+] or Owes [-])
                    $adjustedPaid = $rawPaid + $broughtForward;

                    // Closing (Net) calculation
                    if (isset($row['closing_net']) && is_numeric($row['closing_net'])) {
                        $closingNet = (float) $row['closing_net'];
                    } elseif (isset($row['closing']) && is_numeric($row['closing'])) {
                        $closingNet = (float) $row['closing'];
                    } else {
                        $closingNet = $adjustedPaid - $mealCost;
                    }

                    $due = $closingNet < -0.01 ? abs($closingNet) : 0;
                    $advance = $closingNet > 0.01 ? $closingNet : 0;
                @endphp
                <tr>
                    <td style="text-align: left;">{{ $row['name'] }}</td>
                    <td class="num" style="text-align: center;">{{ number_format((float) ($row['meals'] ?? 0), 1) }}</td>
                    <td class="num">{{ $formatTk($mealCost) }}</td>
                    <td class="num">
                        @if ($adjustedPaid < -0.001)
                            <span class="text-red">{{ $formatTk($adjustedPaid) }}</span>
                        @else
                            {{ $formatTk($adjustedPaid) }}
                        @endif
                    </td>
                    
                    <!-- Closing (Net) Due -->
                    <td class="num">
                        @if ($due > 0)
                            <span class="text-red">{{ $formatTk($due) }}</span>
                        @else
                            {{ $formatTk(0) }}
                        @endif
                    </td>

                    <!-- Closing (Net) Advance -->
                    <td class="num">
                        @if ($advance > 0)
                            <span class="text-green">{{ $formatTk($advance) }}</span>
                        @else
                            {{ $formatTk(0) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection