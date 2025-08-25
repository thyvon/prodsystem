@php($header = 'Stock Request')
@extends('layouts.main')

@section('content')
    <stock-request-show
        :stock='@json($stockRequest)'
        :approvals='@json($approvals)'
        :show-approval-button='@json($showApprovalButton)'
        :total-quantity='@json($totalQuantity)'
        :total-value='@json($totalValue)'
        approval-request-type="{{ $approvalRequestType }}"
        submit-url="{{ route('api.stock-requests.submit-approval', $stockRequest->id) }}"
    />
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