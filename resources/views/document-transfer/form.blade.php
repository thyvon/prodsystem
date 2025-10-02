@php($header = 'Document Transfer')
@extends('layouts.main')

@section('content')
<document-transfer-form
    @if(isset($documentTransfer))
        :document-transfer-id="{{ $documentTransfer->id }}"
    @endif
/>
@endsection

@push('vite')
  @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush

@push('styles')
  <link rel="stylesheet" media="screen, print" href="{{ asset('template/css/formplugins/select2/select2.bundle.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('template/js/formplugins/select2/select2.bundle.js') }}"></script>
@endpush
