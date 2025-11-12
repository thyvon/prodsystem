@php($header = 'File Center')
@extends('layouts.main')

@section('content')
  <file-center-list/>
@endsection

@push('vite')
  @vite(['resources/css/app.css','resources/js/app.js'])
@endpush

@push('styles')
  <!-- <link rel="stylesheet" href="{{ asset('template/css/datagrid/datatables/datatables.bundle.css') }}"> -->
@endpush

@push('scripts')
  <!-- <script src="{{ asset('template/js/datagrid/datatables/datatables.bundle.js') }}"></script> -->
@endpush