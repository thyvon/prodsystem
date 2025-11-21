@php($header = 'Monthly Stock Report')
@extends('layouts.main')

@section('content')
  <monthly-stock-report-show
      :monthly-stock-report-id="{{ $monthlyStockReportId }}"
      approval-request-type="{{ $approvalRequestType }}"
  ></monthly-stock-report-show>
@endsection

@push('vite')
    @vite(['resources/js/app.js'])
@endpush

@push('styles')
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
@endpush