@php($header = 'Dashboard')
@extends('layouts.main')

@section('content')
    <div id="app">
    <dashboard
        auth-name="{{ auth()->user()->name }}">
    </dashboard>
    </div>
@endsection

@push('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush
