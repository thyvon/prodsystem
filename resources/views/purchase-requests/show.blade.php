@php($header = 'Purchase Request')
@extends('layouts.main')

@section('content')
  <purchase-request-show
      :purchase-request-id="{{ $purchaseRequestId }}"
      :initial-data='@json($purchaseRequest)'
  ></purchase-request-show>
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
