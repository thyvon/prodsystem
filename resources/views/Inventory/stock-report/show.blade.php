@php($header = 'Monthly Stock Report')
@extends('layouts.main')

{{-- ========================================== --}}
{{-- 1. LOAD CUSTOM FONTS (TW Cen MT + Khmer OS Battambang) --}}
{{-- ========================================== --}}
@push('styles')
    <style>
        /* TW Cen MT */
        @font-face {
            font-family: 'TW Cen MT';
            src: url('/fonts/TWCenMT-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'TW Cen MT';
            src: url('/fonts/TWCenMT-Bold.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }

        /* Khmer OS Battambang */
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url('/fonts/KhmerOSbattambang-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url('/fonts/KhmerOSbattambang-Bold.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }

        /* Apply beautiful report font to the entire page */
        .monthly-stock-report-page,
        .monthly-stock-report-page * {
            font-family: 'TW Cen MT', 'Khmer OS Battambang', Arial, sans-serif !important;
        }
    </style>

    <!-- Select2 CSS (already there) -->
    <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
@endpush

{{-- ========================================== --}}
{{-- 2. CONTENT WITH FONT CLASS --}}
{{-- ========================================== --}}
@section('content')
    <div class="monthly-stock-report-page">   {{-- ← This makes the font work everywhere --}}
        <monthly-stock-report-show
            :monthly-stock-report-id="{{ $monthlyStockReportId }}"
            approval-request-type="{{ $approvalRequestType }}"
        ></monthly-stock-report-show>
    </div>
@endsection

{{-- ========================================== --}}
{{-- 3. VITE & SCRIPTS --}}
{{-- ========================================== --}}
@push('vite')
    @vite(['resources/js/app.js'])
@endpush

@push('scripts')
    <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
@endpush