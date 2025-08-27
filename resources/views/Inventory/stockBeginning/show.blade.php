@php($header = 'Stock Beginning')
@extends('layouts.main')

@section('content')
    <stock-beginning-show
        :stock='@json($mainStockBeginning)'
        :approvals='@json($approvals)'
        :show-approval-button='@json($showApprovalButton)'
        :total-quantity='@json($totalQuantity)'
        :total-value='@json($totalValue)'
        approval-request-type="{{ $approvalRequestType }}"
        submit-url="{{ route('api.stock-beginnings.submit-approval', $mainStockBeginning->id) }}"
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