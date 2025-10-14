@php($header = 'Digital Document Approval')
@extends('layouts.main')

@section('content')
    <digital-docs-approval-show
        :digital-doc='@json($digitalDocsApproval)'
        :approvals='@json($approvals)'
        :show-approval-button='@json($showApprovalButton)'
        approval-request-type="{{ $approvalRequestType }}"
        submit-url="{{ route('api.digital-docs-approvals.submit-approval', $digitalDocsApproval->id) }}"
        :stream-url='@json(route("digital-approval.view-file", $digitalDocsApproval->id))'
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
