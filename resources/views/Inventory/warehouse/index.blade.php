@php
    $header = 'Warehouse';
    $canCreateWarehouse = auth()->check() && auth()->user()->hasPermissionTo('warehouse.create');
    $canUpdateWarehouse = auth()->check() && auth()->user()->hasPermissionTo('warehouse.update');
    $canDeleteWarehouse = auth()->check() && auth()->user()->hasPermissionTo('warehouse.delete');
@endphp

@extends('layouts.main')

@section('content')
  <warehouse-page
    :page-length="{{ $pageLength ?? 10 }}"
    :can-create-warehouse="{{ json_encode($canCreateWarehouse) }}"
    :can-update-warehouse="{{ json_encode($canUpdateWarehouse) }}"
    :can-delete-warehouse="{{ json_encode($canDeleteWarehouse) }}"
  />
@endsection

@push('vite')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush

@push('styles')
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
  <link rel="stylesheet" href="{{ asset('template/css/datagrid/datatables/datatables.bundle.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
  <script src="{{ asset('template/js/datagrid/datatables/datatables.bundle.js') }}"></script>
@endpush