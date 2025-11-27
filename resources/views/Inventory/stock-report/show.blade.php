@php($header = 'Monthly Stock Report')
@extends('layouts.main')

@push('styles')
<style>
    /* TW Cen MT */
    @font-face {
        font-family: 'TW Cen MT';
        src: url('/fonts/TwCenMT.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    /* Khmer OS Battambang */
    @font-face {
        font-family: 'Khmer OS Battambang';
        src: url('/fonts/KhmerOSBattambang-Regular.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    @font-face {
        font-family: 'Khmer OS Battambang';
        src: url('/fonts/KhmerOSBattambang-Bold.ttf') format('truetype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }

    /* Apply to entire page */
    .monthly-stock-report-page,
    .monthly-stock-report-page * {
        font-family: 'Khmer OS Battambang', 'TW Cen MT', Arial, sans-serif !important;
    }
</style>

<link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
@endpush


@section('content')
<div class="monthly-stock-report-page">
    <monthly-stock-report-show
        :monthly-stock-report-id="{{ $monthlyStockReportId }}"
        approval-request-type="{{ $approvalRequestType }}"
    ></monthly-stock-report-show>
</div>
@endsection


@push('vite')
    @vite(['resources/js/app.js'])
@endpush

@push('scripts')
<script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
@endpush