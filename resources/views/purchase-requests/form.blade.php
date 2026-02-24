@php($header = 'Purchase Request Form')
@extends('layouts.main')

@section('content')
  <purchase-request-form
      :purchase-request-id="{{ $purchaseRequest->id ?? 'null' }}"
      :purchase-request='@json($purchaseRequest)'
      :requester='@json($requester)'
      :user-default-department='@json($userDefaultDepartment)'
      :user-default-campus='@json($userDefaultCampus)'
  />
@endsection

@push('vite')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush

@push('styles')
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/bootstrap-datepicker/bootstrap-datepicker.css') }}">
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
  <link rel="stylesheet" href="{{ asset('template/css/datagrid/datatables/datatables.bundle.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('template/js/formplugins/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
  <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
  <script src="{{ asset('template/js/datagrid/datatables/datatables.bundle.js') }}"></script>
@endpush
