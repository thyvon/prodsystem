@php($header = 'Stock Beginning')
@extends('layouts.main')

@section('content')
  <stock-beginning-form />
@endsection

@push('vite')
  @vite(['resources/css/app.css','resources/js/app.js'])
@endpush

@push('styles')
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/bootstrap-datepicker/bootstrap-datepicker.css') }}">
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('template/js/formplugins/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
  <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
@endpush