@php($header = 'Stock Transfer')
@extends('layouts.main')

@section('content')
    <stock-transfer-show
        :stock='@json($stockTransfer)'
        :approvals='@json($approvals)'
        :show-approval-button='@json($showApprovalButton)'
        :total-quantity='@json($totalQuantity)'
        :total-value='@json($totalValue)'
        approval-request-type="{{ $approvalRequestType }}"
        submit-url="{{ route('api.stock-transfers.submit-approval', $stockTransfer->id) }}"
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