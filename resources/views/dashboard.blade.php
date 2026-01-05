@php($header = 'Dashboard')
@extends('layouts.main')

@section('content')
    <div id="app">
        <dashboard 
            message="សូមស្វាគមន៍ {{ auth()->user()->name }} !">
        </dashboard>
    </div>
@endsection

@push('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush